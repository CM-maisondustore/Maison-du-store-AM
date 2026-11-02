<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Intervention, Contact, User, Quote, Appointment};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};
use Carbon\Carbon;
class InterventionController
{
    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|in:planned,in_progress,completed,cancelled', 'agent' => 'nullable|integer']);
        $q = Intervention::with(['contact', 'agent'])->orderByDesc('scheduled_at');
        if (!$r->user()->isAdmin()) $q->where('agent_id', $r->user()->id);
        elseif (!empty($filters['agent'])) $q->where('agent_id', $filters['agent']);
        if (!empty($filters['q'])) $q->where(fn ($b) => $b->where('address', 'like', '%'.$filters['q'].'%')->orWhereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$filters['q'].'%')));
        $counts = (clone $q)->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        return view('staff.interventions', ['counts' => $counts, 'jobs' => $q->paginate(25)->withQueryString(), 'contacts' => $r->user()->isAdmin() ? Contact::whereNull('archived_at')->orderBy('name')->get() : collect(), 'agents' => $r->user()->isAdmin() ? User::where('role', 'agent')->where('active', true)->get() : collect(), 'quotes' => $r->user()->isAdmin() ? Quote::with('customer')->whereNotNull('contact_id')->latest()->limit(200)->get() : collect(), 'appointments' => $r->user()->isAdmin() ? Appointment::with('customer')->whereNotNull('contact_id')->latest()->limit(200)->get() : collect()]);
    }
    public function show(Request $r, Intervention $intervention)
    {
        abort_unless($r->user()->isAdmin() || $intervention->agent_id === $r->user()->id, 403);
        return view('staff.intervention', ['job' => $intervention, 'agents' => $r->user()->isAdmin() ? User::where('role', 'agent')->where('active', true)->get() : collect()]);
    }
    public function store(Request $r)
    {
        $data = $this->planning($r) + $r->validate(['contact_id' => 'required|exists:contacts,id', 'quote_id' => 'nullable|exists:quotes,id', 'appointment_id' => 'nullable|exists:appointments,id', 'kind' => 'required|in:devis_sur_place,installation,sav']);
        foreach (['quote_id' => Quote::class, 'appointment_id' => Appointment::class] as $field => $model) if (!empty($data[$field]) && $model::findOrFail($data[$field])->contact_id !== (int) $data['contact_id']) throw ValidationException::withMessages([$field => 'Le document appartient à un autre client.']);
        $job = DB::transaction(function () use ($data) { $this->checkSlot($data); return Intervention::create($data); });
        Audit::record('intervention_creee', $job);
        app(\App\Services\StaffPushNotifications::class)->send(
            [User::findOrFail($job->agent_id)], 'Nouvelle prestation affectée', 'Une prestation vous est affectée le '.$job->scheduled_at->format('d/m/Y à H:i').'.', route('staff.interventions.show', $job),
        );
        return redirect()->route('staff.interventions.show', $job);
    }
    public function update(Request $r, Intervention $intervention)
    {
        abort_unless($r->user()->isAdmin() || $intervention->agent_id === $r->user()->id, 403);
        $data = $r->validate(['status' => 'required|in:planned,in_progress,completed,cancelled', 'report' => 'nullable|required_if:status,completed|string|max:10000']);
        if (!$r->user()->isAdmin()) {
            abort_if($r->input('status') === 'cancelled' || in_array($intervention->status, ['completed', 'cancelled']), 403);
        } else $data += $this->planning($r);
        DB::transaction(function () use ($intervention, $data, $r) {
            if ($r->user()->isAdmin() && $data['status'] !== 'cancelled') $this->checkSlot($data, $intervention->id);
            $job = Intervention::whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user()->isAdmin() || ($job->agent_id === $r->user()->id && !in_array($job->status, ['completed', 'cancelled'])), 403);
            $job->update([...$data, 'completed_at' => $data['status'] === 'completed' ? ($job->completed_at ?? now()) : null]);
            Audit::record('intervention_modifiee', $job);
        });
        if ($r->user()->isAdmin()) {
            $intervention->refresh();
            app(\App\Services\StaffPushNotifications::class)->send(
                [User::findOrFail($intervention->agent_id)], 'Prestation mise à jour', 'Les informations de votre prestation ont été mises à jour.', route('staff.interventions.show', $intervention),
            );
        }
        return back()->with('success', 'Prestation enregistrée.');
    }
    private function planning(Request $r): array
    {
        $data = $r->validate(['agent_id' => ['required', Rule::exists('users', 'id')->where('active', true)->where('role', 'agent')], 'scheduled_at' => 'required|date_format:Y-m-d\TH:i', 'duration_minutes' => 'required|integer|min:15|max:480', 'address' => 'required|string|max:1000', 'instructions' => 'nullable|string|max:10000']);
        // HTML form values remain strings after validation; Carbon requires a number.
        $data['duration_minutes'] = (int) $data['duration_minutes'];
        return $data;
    }
    private function checkSlot(array $data, ?int $except = null): void
    {
        User::whereKey($data['agent_id'])->lockForUpdate()->firstOrFail();
        $start = Carbon::parse($data['scheduled_at']); $end = $start->copy()->addMinutes($data['duration_minutes']);
        $jobs = Intervention::where('agent_id', $data['agent_id'])->whereNotIn('status', ['cancelled', 'completed'])->when($except, fn ($q) => $q->where('id', '!=', $except))->where('scheduled_at', '<', $end)->where('scheduled_at', '>', $start->copy()->subHours(8))->get();
        foreach ($jobs as $job) if ($job->scheduled_at->copy()->addMinutes($job->duration_minutes)->gt($start)) throw ValidationException::withMessages(['scheduled_at' => 'Cet agent a déjà une prestation sur ce créneau.']);
    }
}

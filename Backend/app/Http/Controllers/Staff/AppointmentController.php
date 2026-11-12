<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Appointment, User};
use App\Services\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule, ValidationException};
class AppointmentController
{
    public function index(Request $r)
    {
        $filters = $r->validate(['vue' => 'nullable|in:semaine,jour,liste', 'date' => 'nullable|date_format:Y-m-d', 'status' => 'nullable|in:pending,confirmed,completed,cancelled']);
        $state = \App\Services\Trash::state($r);
        // Archivés et corbeille : toujours en liste.
        $view = $state ? 'liste' : ($filters['vue'] ?? 'semaine');
        $q = Appointment::with('agent')->listState($state)->orderByDesc('date')->orderBy('time');
        if (!$r->user()->isAdmin()) $q->where('agent_id', $r->user()->id);
        $anchor = \Illuminate\Support\Carbon::parse($filters['date'] ?? today());
        $start = $view === 'jour' ? $anchor->copy()->startOfDay() : $anchor->copy()->startOfWeek();
        if ($view === 'liste') {
            if (!empty($filters['status'])) $q->where('status', $filters['status']);
            return view('staff.appointments', ['state' => $state, 'view' => $view, 'start' => $start, 'days' => [], 'appointments' => \App\Services\Trash::order($q, $state)->paginate(25)->withQueryString()]);
        }
        $length = $view === 'jour' ? 1 : 6;
        $events = $q->whereBetween('date', [$start->toDateString(), $start->copy()->addDays($length - 1)->toDateString()])->where('status', '!=', 'cancelled')->reorder('time')->get();
        $days = [];
        for ($i = 0; $i < $length; $i++) { $date = $start->copy()->addDays($i); $days[] = ['date' => $date, 'events' => $events->where('date', $date->toDateString())]; }
        return view('staff.appointments', ['state' => $state, 'view' => $view, 'start' => $start, 'days' => $days, 'appointments' => collect()]);
    }
    public function show(Request $r, Appointment $appointment)
    {
        abort_unless($r->user()->isAdmin() || $appointment->agent_id === $r->user()->id, 403);
        return view('staff.appointment', ['appointment' => $appointment, 'agents' => User::where('role', 'agent')->where('active', true)->get()]);
    }
    public function update(Request $r, Appointment $appointment)
    {
        $data = $r->validate(['date' => 'required|date_format:Y-m-d', 'time' => ['required', Rule::in(config('appointments.slots'))], 'status' => 'required|in:pending,confirmed,completed,cancelled', 'agent_id' => ['nullable', Rule::exists('users', 'id')->where('active', true)->where('role', 'agent')], 'internal_notes' => 'nullable|string|max:10000']);
        try { $appointment->update($data); } catch (UniqueConstraintViolationException $e) { throw ValidationException::withMessages(['time' => 'Ce créneau est déjà réservé.']); }
        Audit::record('rendez_vous_modifie', $appointment);
        return back()->with('success', 'Rendez-vous enregistré. Une annulation libère le créneau.');
    }
}

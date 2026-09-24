<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Quote, User, Intervention};
use App\Services\{Audit, Pricing, Documents};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class QuoteController
{
    private function access(Request $r, Quote $quote): void { abort_unless($r->user()->isAdmin() || $quote->agent_id === $r->user()->id, 403); }
    public function index(Request $r)
    {
        $query = Quote::with('agent')->latest();
        if (!$r->user()->isAdmin()) $query->where('agent_id', $r->user()->id);
        if ($r->filled('status')) $query->where('status', $r->string('status')->toString());
        return view('staff.quotes', ['quotes' => $query->paginate(25)->withQueryString()]);
    }
    public function show(Request $r, Quote $quote) { $this->access($r, $quote); return view('staff.quote', ['quote' => $quote, 'agents' => User::where('role', 'agent')->where('active', true)->get()]); }
    public function onsite(Request $r, Intervention $intervention)
    {
        abort_unless($r->user()->isAdmin() || $intervention->agent_id === $r->user()->id, 403);
        $r->validate(['type' => 'required|in:store,rideau']);
        $quote = DB::transaction(function () use ($intervention, $r) {
            $job = Intervention::whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            if ($job->quote_id) return Quote::findOrFail($job->quote_id);
            $contact = $job->contact;
            $quote = Quote::create(['reference' => (string) Str::uuid(), 'type' => $r->input('type'), 'source' => 'sur_place', 'status' => 'draft', 'contact_id' => $contact->id, 'agent_id' => $job->agent_id, 'contact' => ['firstName' => $contact->name, 'lastName' => '', 'email' => $contact->email, 'phone' => $contact->phone, 'address' => $contact->address], 'product' => [], 'dimensions' => []]);
            $job->update(['quote_id' => $quote->id]); Audit::record('devis_sur_place_cree', $quote);
            return $quote;
        });
        return redirect()->route('staff.quotes.show', $quote);
    }
    public function update(Request $r, Quote $quote)
    {
        $this->access($r, $quote);
        $data = [...Pricing::validate($r), ...$r->validate(['internal_notes' => 'nullable|string|max:10000', 'valid_until' => 'required|date_format:Y-m-d', 'dimensions' => 'nullable|array|max:8', 'dimensions.*.label' => 'required|string|max:100', 'dimensions.*.width' => 'required|numeric|min:20|max:400', 'dimensions.*.height' => 'required|numeric|min:20|max:400', 'dimensions.*.quantity' => 'required|integer|min:1|max:20'])];
        if ($r->user()->isAdmin()) $data += $r->validate(['status' => 'required|in:pending,draft,approved,accepted,rejected,cancelled', 'agent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'agent')->where('active', true)]]);
        DB::transaction(function () use ($quote, $r, $data) {
            $locked = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->access($r, $locked);
            abort_unless(in_array($locked->status, ['pending', 'draft']) || $r->user()->isAdmin(), 403);
            abort_if($locked->status === 'accepted' || \App\Models\Invoice::where('quote_id', $locked->id)->exists(), 409, 'Devis accepté ou facturé : document verrouillé.');
            $locked->update($data); Audit::record('devis_modifie', $locked);
        });
        return back()->with('success', 'Devis enregistré.');
    }
    public function pdf(Request $r, Quote $quote, Documents $documents) { $this->access($r, $quote); return response($documents->quote($quote))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="devis-'.$quote->reference.'.pdf"'); }
}

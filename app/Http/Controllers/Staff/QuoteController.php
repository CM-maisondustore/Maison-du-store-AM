<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Quote, User, Intervention};
use App\Services\{Audit, Pricing, Documents};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class QuoteController
{
    private function access(Request $r, Quote $quote): void { abort_unless($quote->canBeViewedBy($r->user()), 403); }
    /** Photo d'une ouverture : visible par qui peut voir le devis, et seulement si elle appartient à ce devis. */
    public function photo(Request $r, Quote $quote, int $media)
    {
        $this->access($r, $quote);
        abort_unless(in_array($media, \App\Services\QuotePhotos::ids($quote), true), 404);
        $asset = \App\Models\MediaAsset::findOrFail($media);
        return response(base64_decode($asset->payload), 200, ['Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'"]);
    }
    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:150', 'type' => 'nullable|in:store,rideau,electromenager,meubles', 'source' => 'nullable|in:site,interne,sur_place', 'agent' => 'nullable|string|max:20', 'sort' => 'nullable|in:asc,desc']);
        $query = Quote::with('agent')->visibleTo($r->user());
        $query = ($filters['sort'] ?? 'desc') === 'asc' ? $query->oldest() : $query->latest();
        if ($r->filled('status')) $query->where('status', $r->string('status')->toString());
        if (!empty($filters['q'])) $query->where(fn ($b) => $b->where('reference', 'like', '%'.$filters['q'].'%')->orWhereRaw('LOWER(CAST(contact AS CHAR)) LIKE ?', ['%'.mb_strtolower($filters['q']).'%']));
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['source'])) $query->where('source', $filters['source']);
        if ($r->user()->isAdmin() && !empty($filters['agent'])) $filters['agent'] === 'none' ? $query->whereNull('agent_id') : $query->where('agent_id', (int) $filters['agent']);
        $counts = Quote::visibleTo($r->user())->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        return view('staff.quotes', ['quotes' => $query->paginate(25)->withQueryString(), 'counts' => $counts, 'agents' => $r->user()->isAdmin() ? User::where('role', 'agent')->where('active', true)->orderBy('name')->get() : collect()]);
    }
    public function show(Request $r, Quote $quote) { $this->access($r, $quote); return view($r->user()->isAdmin() ? 'staff.quote' : 'staff.quote-technical', ['quote' => $quote, 'agents' => User::where('role', 'agent')->where('active', true)->get()]); }
    public function onsite(Request $r, Intervention $intervention)
    {
        abort_unless($r->user()->isAdmin() || $intervention->agent_id === $r->user()->id, 403);
        $r->validate(['type' => 'required|in:store,rideau,electromenager,meubles']);
        $quote = DB::transaction(function () use ($intervention, $r) {
            $job = Intervention::whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user()->isAdmin() || $job->agent_id === $r->user()->id,403);
            if ($job->quote_id) return Quote::findOrFail($job->quote_id);
            $contact = $job->contact;
            $quote = Quote::create(['type' => $r->input('type'), 'created_by'=>$r->user()->id, 'source' => 'sur_place', 'status' => 'draft', 'contact_id' => $contact->id, 'agent_id' => $job->agent_id, 'contact' => ['firstName' => $contact->name, 'lastName' => '', 'email' => $contact->email, 'phone' => $contact->phone, 'address' => $contact->address], 'product' => [], 'dimensions' => []]);
            $job->update(['quote_id' => $quote->id]); Audit::record('devis_sur_place_cree', $quote);
            return $quote;
        });
        $this->access($r,$quote);
        $canEdit=($r->user()->isAdmin() || $quote->created_by===$r->user()->id) && in_array($quote->status,['pending','draft']);
        return redirect()->route($canEdit ? 'staff.quotes.edit' : 'staff.quotes.show', $quote);
    }
    public function update(Request $r, Quote $quote)
    {
        abort_unless($r->user()->isAdmin(),403);
        $this->access($r, $quote);
        $r->validate(['edit_version'=>'sometimes|required|integer|min:0']);
        $data = [...Pricing::validate($r), ...$r->validate(['internal_notes' => 'nullable|string|max:10000', 'valid_until' => 'required|date_format:Y-m-d', 'dimensions' => 'nullable|array|max:8', 'dimensions.*.label' => 'required|string|max:100', 'dimensions.*.width' => 'required|numeric|min:20|max:400', 'dimensions.*.height' => 'required|numeric|min:20|max:400', 'dimensions.*.quantity' => 'required|integer|min:1|max:20'])];
        if ($r->user()->isAdmin()) $data += $r->validate(['status' => 'required|in:pending,draft,approved,accepted,rejected,cancelled', 'agent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'agent')->where('active', true)]]);
        DB::transaction(function () use ($quote, $r, $data) {
            $locked = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->access($r, $locked);
            if ($r->has('edit_version') && $locked->edit_version!==(int)$r->input('edit_version')) throw \Illuminate\Validation\ValidationException::withMessages(['edit_version'=>'Ce devis a été modifié. Rechargez-le avant d’enregistrer.']);
            abort_unless(in_array($locked->status, ['pending', 'draft']) || $r->user()->isAdmin(), 403);
            abort_if($locked->status === 'accepted' || \App\Models\Invoice::where('quote_id', $locked->id)->exists(), 409, 'Devis accepté ou facturé : document verrouillé.');
            if ($locked->configuration) unset($data['dimensions']);
            if (!empty($locked->configuration['calculation']['financial'])) {
                if ($data['lines']!=$locked->lines || $data['tax_basis_points']!==$locked->tax_basis_points) throw \Illuminate\Validation\ValidationException::withMessages(['lines'=>'Modifiez le chiffrage dans le formulaire complet afin de conserver les remises et les acomptes cohérents.']);
            }
            if (in_array($data['status'] ?? '',['approved','accepted']) && !empty($locked->configuration['pricing_review_required'])) throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Les choix techniques ont changé. Vérifiez le chiffrage dans le formulaire complet avant validation.']);
            if (in_array($data['status'] ?? '',['approved','accepted']) && !empty($locked->configuration['calculation']['pending'])) throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Des prix restent à définir (notamment la pose). Complétez puis recalculez le devis dans le formulaire complet avant validation.']);
            $total=$locked->configuration['calculation']['financial']['total'] ?? Pricing::totals($data['lines'],$data['tax_basis_points'])['total'];
            if ($locked->receivedAmount()>$total) throw \Illuminate\Validation\ValidationException::withMessages(['lines'=>'Le total ne peut pas être inférieur aux paiements reçus.']);
            if ($locked->deposit_amount>$total) throw \Illuminate\Validation\ValidationException::withMessages(['lines'=>'Le total ne peut pas être inférieur à l’avance prévue. Modifiez les conditions dans le formulaire complet.']);
            $locked->update([...$data,'edit_version'=>$locked->edit_version+1]); Audit::record('devis_modifie', $locked);
        });
        return back()->with('success', 'Devis enregistré.');
    }
    public function pdf(Request $r, Quote $quote, Documents $documents) { abort_unless($r->user()->isAdmin(),403); $this->access($r, $quote); return response($documents->quote($quote))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="devis-'.$quote->reference.'.pdf"'); }
}

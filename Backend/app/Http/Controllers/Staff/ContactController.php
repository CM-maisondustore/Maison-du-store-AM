<?php
namespace App\Http\Controllers\Staff;
use App\Models\Contact;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ContactController
{
    public function index(Request $r)
    {
        // Ancien filtre « type=archive » : renvoie vers l'onglet Archivés.
        if ($r->query('type') === 'archive') return redirect()->route('staff.contacts.index', ['etat' => 'archives']);
        $state = \App\Services\Trash::state($r);
        $query = Contact::query()->listState($state);
        if ($q = $r->string('q')->toString()) $query->where(fn ($b) => $b->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'));
        $type = $r->string('type')->toString();
        if (in_array($type, ['client', 'fournisseur', 'partenaire'], true)) $query->where('kind', $type);
        $stats = Contact::listState($state)->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind')->all();
        return view('staff.contacts', ['state' => $state, 'contacts' => \App\Services\Trash::order($query->orderBy('name'), $state)->paginate(25)->withQueryString(), 'stats' => $stats, 'total' => array_sum($stats)]);
    }
    public function show(Contact $contact) { return view('staff.contact', compact('contact')); }
    public function store(Request $r) { $contact = Contact::create($this->data($r)); Audit::record('contact_cree', $contact); return redirect()->route('staff.contacts.show', $contact); }
    public function update(Request $r, Contact $contact) { $contact->update($this->data($r, $contact)); Audit::record('contact_modifie', $contact); return back()->with('success', 'Dossier enregistré.'); }
    private function data(Request $r, ?Contact $contact = null): array
    {
        $r->merge(['email' => $r->filled('email') ? mb_strtolower(trim($r->input('email'))) : null]);
        $data = $r->validate(['name' => 'required|string|max:200', 'kind' => ['required', Rule::in(['client', 'fournisseur', 'partenaire'])], 'email' => ['nullable', 'email', 'max:200', Rule::unique('contacts')->ignore($contact)], 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:1000', 'notes' => 'nullable|string|max:10000', 'consent_source' => 'nullable|required_if:marketing,1|string|max:250', 'marketing' => 'nullable|boolean', 'archived' => 'nullable|boolean']);
        return [...collect($data)->except(['marketing', 'archived'])->all(), 'marketing_consent_at' => $r->boolean('marketing') ? ($contact?->marketing_consent_at ?? now()) : null, 'archived_at' => $r->boolean('archived') ? ($contact?->archived_at ?? now()) : null];
    }
}

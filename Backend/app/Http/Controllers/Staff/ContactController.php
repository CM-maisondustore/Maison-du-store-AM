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
        $query = Contact::query();
        if ($q = $r->string('q')->toString()) $query->where(fn ($b) => $b->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'));
        return view('staff.contacts', ['contacts' => $query->orderBy('name')->paginate(25)->withQueryString()]);
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

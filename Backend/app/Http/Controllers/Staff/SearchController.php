<?php
namespace App\Http\Controllers\Staff;

use App\Models\{Contact,Intervention,Invoice,Quote};
use App\Services\StaffInterface;
use Illuminate\Http\Request;

/** Recherche globale. Un agent ne trouve que ses propres devis et prestations, jamais un montant. */
class SearchController
{
    public function __invoke(Request $r)
    {
        $q = trim((string) $r->validate(['q' => 'nullable|string|max:150'])['q'] ?? '');
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $user = $r->user();
            $groups['Devis'] = Quote::visibleTo($user)->where(fn ($b) => $b->where('reference', 'like', $like)->orWhereRaw('LOWER(CAST(contact AS CHAR)) LIKE ?', [mb_strtolower($like)]))->latest()->limit(10)->get()
                ->map(fn ($quote) => ['title' => StaffInterface::quoteClient($quote), 'sub' => StaffInterface::ref($quote->reference), 'status' => $quote->status, 'href' => route('staff.quotes.show', $quote)]);
            if ($user->isAdmin()) {
                $groups['Clients et contacts'] = Contact::where(fn ($b) => $b->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name')->limit(10)->get()
                    ->map(fn ($contact) => ['title' => $contact->name, 'sub' => trim($contact->phone.' '.$contact->email), 'href' => route('staff.contacts.show', $contact)]);
                $groups['Factures'] = Invoice::with('contact')->where(fn ($b) => $b->where('number', 'like', $like)->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $like)))->latest()->limit(10)->get()
                    ->map(fn ($invoice) => ['title' => $invoice->number ?? 'Brouillon #'.$invoice->id, 'sub' => $invoice->contact->name, 'status' => $invoice->status, 'href' => route('staff.invoices.show', $invoice)]);
            }
            $jobs = Intervention::with('contact')->where(fn ($b) => $b->where('address', 'like', $like)->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $like)));
            if (!$user->isAdmin()) $jobs->where('agent_id', $user->id);
            $groups['Prestations'] = $jobs->orderByDesc('scheduled_at')->limit(10)->get()
                ->map(fn ($job) => ['title' => $job->contact->name, 'sub' => $job->scheduled_at->format('d/m/Y H:i').' · '.(StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind), 'status' => $job->status, 'href' => route('staff.interventions.show', $job)]);
        }
        return view('staff.search', ['q' => $q, 'groups' => $groups]);
    }
}

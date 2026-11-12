<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Invoice, Quote};
use App\Services\{Audit, Documents, Pricing};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class InvoiceController
{
    public function index(Request $r)
    {
        $status = $r->validate(['status' => 'nullable|in:draft,issued,paid'])['status'] ?? null;
        $state = \App\Services\Trash::state($r);
        return view('staff.invoices', ['state' => $state, 'invoices' => \App\Services\Trash::order(Invoice::with('contact')->listState($state)->when($status, fn ($q) => $q->where('status', $status))->latest(), $state)->paginate(25)->withQueryString(), 'counts' => Invoice::listState($state)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')]);
    }
    public function create(Request $r, Quote $quote)
    {
        $data = $r->validate(['due_date' => 'required|date_format:Y-m-d']);
        $invoice = DB::transaction(function () use ($quote, $data) {
            $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless($q->status === 'accepted' && $q->contact_id && count($q->lines ?? []), 422, 'Un devis accepté et chiffré est requis.');
            $old = Invoice::onlyTrashed()->where('quote_id', $q->id)->first();
            if ($old && !\App\Services\Trash::purge($old)) abort(409, 'Une ancienne facture de ce devis est à la corbeille : restaurez-la.');
            return Invoice::firstOrCreate(['quote_id' => $q->id], ['contact_id' => $q->contact_id, 'due_date' => $data['due_date'], 'snapshot' => ['issuer' => config('workspace.issuer'), 'customer' => $q->contact, 'lines' => $q->lines, 'tax_basis_points' => $q->tax_basis_points,
                'quote_reference'=>$q->reference,...($q->document_metadata ?? []),
                'financial'=>\App\Services\Quotes\QuoteFinancials::forQuote($q), 'windows'=>$q->dimensions, 'payment_plan'=>['mode'=>$q->payment_mode,'deposit'=>$q->deposit_amount,'received'=>$q->receivedAmount()]]]);
        });
        Audit::record('facture_preparee', $invoice);
        return redirect()->route('staff.invoices.show', $invoice);
    }
    public function show(Invoice $invoice) { return view('staff.invoice', ['invoice' => $invoice, 'totals' => \App\Services\Quotes\QuoteFinancials::forSnapshot($invoice->snapshot)]); }
    public function issue(Invoice $invoice)
    {
        $issuer=\App\Services\CommunicationTemplates::issuer(\App\Services\CommunicationTemplates::presentation('invoice'));
        foreach (['name', 'address', 'registration', 'tax_note', 'payment_terms'] as $key) if (empty($issuer[$key])) throw ValidationException::withMessages(['issuer' => 'Complétez les informations de facturation dans Documents & automatisations, modèle Facture PDF, avant émission.']);
        DB::transaction(function () use ($invoice) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409);
            $snapshot = $locked->snapshot; $snapshot['presentation']=\App\Services\CommunicationTemplates::presentation('invoice');
            $source=Quote::whereKey($locked->quote_id)->lockForUpdate()->first();
            if ($source) {
                $snapshot['quote_reference']=$snapshot['quote_reference'] ?? $source->reference;
                $snapshot['payment_plan']=[...($snapshot['payment_plan'] ?? []),'received'=>$source->receivedAmount()];
            }
            $snapshot['issuer'] = \App\Services\CommunicationTemplates::issuer($snapshot['presentation']);
            $locked->update(['number' => \App\Services\DocumentReferences::invoice($source ?? Quote::findOrFail($locked->quote_id)), 'snapshot' => $snapshot, 'status' => 'issued', 'issued_at' => now()]);
            \App\Models\DocumentArchive::capture(app(Documents::class)->render(app(Documents::class)->invoiceData($locked)),['invoice_id'=>$locked->id]);
            Audit::record('facture_emise', $locked);
        });
        return back()->with('success', 'Facture émise et verrouillée.');
    }
    public function paid(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) { $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail(); abort_unless($locked->status === 'issued', 409); $locked->update(['status' => 'paid', 'paid_at' => now()]); Audit::record('facture_payee', $locked); });
        return back()->with('success', 'Paiement enregistré.');
    }
    public function pdf(Invoice $invoice, Documents $documents) { return response($documents->invoice($invoice))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="facture-'.($invoice->number ?? $invoice->id).'.pdf"'); }
}

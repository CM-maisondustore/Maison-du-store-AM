<?php
namespace App\Services;
use App\Models\{Quote, Invoice};
use Dompdf\{Dompdf, Options};
class Documents
{
    public function quote(Quote $quote): string
    {
        return $this->render($this->quoteData($quote));
    }
    public function quoteData(Quote $quote): array
    {
        $presentation=CommunicationTemplates::presentation('quote');
        return ['presentation'=>$presentation, 'title' => 'Devis', 'number' => $quote->reference, 'draft' => !in_array($quote->status, ['approved', 'accepted']), 'date' => $quote->quote_date ? \Carbon\Carbon::parse($quote->quote_date)->format('d/m/Y') : $quote->created_at->format('d/m/Y'), 'due' => $quote->valid_until, 'issuer' => CommunicationTemplates::issuer($presentation), 'customer' => $quote->contact, 'lines' => $quote->lines ?? [], 'tax_basis_points' => $quote->tax_basis_points,
            'windows'=>$quote->configuration ? $quote->dimensions : [], 'pending_costs'=>$quote->configuration['calculation']['pending'] ?? [], 'schedule'=>['delivery'=>$quote->delivery_date,'installation'=>$quote->installation_date],
            'payment_plan'=>['mode'=>$quote->payment_mode,'deposit'=>$quote->deposit_amount,'received'=>$quote->receivedAmount()]];
    }
    public function invoice(Invoice $invoice): string
    {
        if ($archive=\App\Models\DocumentArchive::where('invoice_id',$invoice->id)->first()) return $archive->contents();
        return $this->render($this->invoiceData($invoice));
    }
    public function invoiceData(Invoice $invoice): array
    {
        $presentation=$invoice->issued_at ? ($invoice->snapshot['presentation'] ?? []) : CommunicationTemplates::presentation('invoice');
        return [...$invoice->snapshot, 'issuer'=>$invoice->issued_at ? $invoice->snapshot['issuer'] : CommunicationTemplates::issuer($presentation), 'presentation'=>$presentation, 'title' => 'Facture', 'number' => $invoice->number ?? 'BROUILLON-'.$invoice->id, 'draft' => !$invoice->issued_at, 'date' => ($invoice->issued_at ?? $invoice->created_at)->format('d/m/Y'), 'due' => $invoice->due_date->format('d/m/Y')];
    }
    public function render(array $data): string
    {
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'tempDir' => storage_path('framework/cache'), 'fontCache' => storage_path('framework/cache')]);
        $pdf = new Dompdf($options); $pdf->loadHtml(view('staff.document', [...$data, 'totals' => Pricing::totals($data['lines'], $data['tax_basis_points'])])->render());
        $pdf->setPaper('A4'); $pdf->render(); return $pdf->output();
    }
}

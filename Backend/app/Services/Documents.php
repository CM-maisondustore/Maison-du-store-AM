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
        return ['title' => 'Devis', 'number' => $quote->reference, 'draft' => !in_array($quote->status, ['approved', 'accepted']), 'date' => $quote->created_at->format('d/m/Y'), 'due' => $quote->valid_until, 'issuer' => config('workspace.issuer'), 'customer' => $quote->contact, 'lines' => $quote->lines ?? [], 'tax_basis_points' => $quote->tax_basis_points];
    }
    public function invoice(Invoice $invoice): string
    {
        return $this->render($this->invoiceData($invoice));
    }
    public function invoiceData(Invoice $invoice): array
    {
        return [...$invoice->snapshot, 'title' => 'Facture', 'number' => $invoice->number ?? 'BROUILLON-'.$invoice->id, 'draft' => !$invoice->issued_at, 'date' => ($invoice->issued_at ?? $invoice->created_at)->format('d/m/Y'), 'due' => $invoice->due_date->format('d/m/Y')];
    }
    public function render(array $data): string
    {
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'tempDir' => storage_path('framework/cache'), 'fontCache' => storage_path('framework/cache')]);
        $pdf = new Dompdf($options); $pdf->loadHtml(view('staff.document', [...$data, 'totals' => Pricing::totals($data['lines'], $data['tax_basis_points'])])->render());
        $pdf->setPaper('A4'); $pdf->render(); return $pdf->output();
    }
}

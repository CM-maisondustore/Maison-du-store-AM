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
        $html=view('staff.document', [...$data, 'totals' => Pricing::totals($data['lines'], $data['tax_basis_points'])])->render();
        $pdf = new Dompdf($options); $pdf->loadHtml($html);
        $pdf->setPaper('A4'); $pdf->render();
        $presentation=$data['presentation'] ?? [];
        if (empty($presentation['letterhead_enabled'])) return $pdf->output();
        // Count with the final text margins, then paint each background BEFORE its text.
        // The identical layout in both passes keeps pagination stable, including the last page.
        $pages=$pdf->getCanvas()->get_page_count(); unset($pdf); gc_collect_cycles();
        $images=[]; $paths=[];
        try {
            foreach (PdfLetterhead::IMAGES as $key) {
                $image=PdfLetterhead::decode($presentation[$key] ?? null);
                if (!$image) throw new \RuntimeException('Fond de page PDF absent ou invalide. Vérifiez le modèle.');
                $path=tempnam(storage_path('framework/cache'),'letterhead-');
                if ($path===false) throw new \RuntimeException('Impossible de préparer le fond de page PDF.');
                $paths[]=$path;
                if (file_put_contents($path,$image['bytes'])===false) throw new \RuntimeException('Impossible d’écrire le fond de page PDF.');
                $images[$key]=['path'=>$path,'width'=>$image['width'],'height'=>$image['height']];
            }
            $pdf=new Dompdf($options); $pdf->loadHtml($html); $pdf->setPaper('A4');
            $opacity=max(10,min(100,(int)($presentation['letterhead_opacity'] ?? 100)))/100;
            $pdf->setCallbacks([['event'=>'begin_page_render','f'=>function ($frame,$canvas) use ($images,$pages,$opacity) {
                $key=$canvas->get_page_number()===$pages ? 'letterhead_standard' : 'letterhead_continuation';
                $image=$images[$key]; $scale=min($canvas->get_width()/$image['width'],$canvas->get_height()/$image['height']);
                $width=$image['width']*$scale; $height=$image['height']*$scale;
                $canvas->save(); $canvas->set_opacity($opacity);
                $canvas->image($image['path'],($canvas->get_width()-$width)/2,($canvas->get_height()-$height)/2,$width,$height);
                $canvas->restore(); $canvas->set_opacity(1);
            }]]);
            $pdf->render(); return $pdf->output();
        } finally {
            foreach ($paths as $path) if (is_file($path)) unlink($path);
        }
    }
}

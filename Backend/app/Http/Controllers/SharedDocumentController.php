<?php
namespace App\Http\Controllers;
use App\Models\CommercialMessage;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Lien signé ajouté à un message WhatsApp : page de téléchargement du document (devis, facture, reçu),
 * puis le PDF archivé lui-même. Un lien expiré ou altéré affiche la page sans rien révéler du document.
 */
class SharedDocumentController
{
    private const KINDS = [
        'Devis' => ['devis', 'Votre devis', 'Valable jusqu’au', 'Montant du devis'],
        'Facture' => ['facture', 'Votre facture', 'À régler avant le', 'Montant à régler'],
        'Reçu' => ['recu', 'Votre reçu', null, 'Montant reçu'],
    ];
    private const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer'];

    public function show(Request $request, int $message)
    {
        $m = $request->hasValidSignature(false) ? $this->shared($message) : null;
        if (!$m) return response()->view('documents.shared', ['doc' => null, 'contact' => config('site.contact')], 410)->withHeaders(self::HEADERS);

        $s = $m->document_snapshot ?? [];
        $title = $s['title'] ?? ($m->invoice_id ? 'Facture' : 'Devis');
        [$slug, $heading, $dueLabel, $amountLabel] = self::KINDS[$title] ?? self::KINDS['Devis'];
        $expires = Carbon::createFromTimestamp((int) $request->query('expires'));
        $file = fn (bool $download) => url(URL::temporarySignedRoute('documents.whatsapp.file', $expires, ['message' => $m->id, ...($download ? ['telecharger' => 1] : [])], false));
        // Total final du chiffrage, ou total des lignes pour un document sans chiffrage détaillé.
        $totals = $slug === 'recu' ? [] : \App\Services\Quotes\QuoteFinancials::forSnapshot($s);
        $amount = $slug === 'recu' ? ($s['receipt']['amount'] ?? null) : ($totals['finalTotal'] ?? $totals['total'] ?? null);

        return response()->view('documents.shared', ['contact' => config('site.contact'), 'doc' => [
            'kind' => $slug, 'title' => $title, 'heading' => $heading,
            'number' => $s['number'] ?? '', 'draft' => !empty($s['draft']),
            'firstName' => trim($s['customer']['firstName'] ?? '') ?: trim($s['customer']['lastName'] ?? ''),
            'date' => $s['date'] ?? null, 'dueLabel' => $dueLabel, 'due' => $dueLabel ? $this->day($s['due'] ?? null) : null,
            'amountLabel' => $amountLabel, 'amount' => is_numeric($amount) ? number_format((float) $amount, 0, ',', ' ').' FCFA' : null,
            'method' => $s['receipt']['method'] ?? null,
            'expires' => $expires->locale('fr')->isoFormat('D MMMM YYYY'),
            'download' => $file(true), 'open' => $file(false),
            'filename' => $this->filename($m),
        ]])->withHeaders(self::HEADERS);
    }

    /** Le PDF archivé (lien signé créé par la page, même date d'expiration). */
    public function file(Request $request, int $message)
    {
        $m = $this->shared($message);
        abort_unless($m, 404);
        Audit::record('document_whatsapp_consulte', $m);
        return response($m->archive->contents())->withHeaders([...self::HEADERS,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('telecharger') ? 'attachment' : 'inline').'; filename="'.$this->filename($m).'"',
        ]);
    }

    private function shared(int $id): ?CommercialMessage
    {
        $m = CommercialMessage::with('archive')->find($id);
        if (!$m || $m->channel !== 'whatsapp' || $m->direction !== 'outgoing' || !in_array($m->status, ['draft', 'shared'], true) || !$m->archive) return null;
        return $m->contact?->archived_at ? null : $m;
    }

    private function filename(CommercialMessage $m): string
    {
        $title = $m->document_snapshot['title'] ?? ($m->invoice_id ? 'Facture' : 'Devis');
        $prefix = ['Facture' => 'Facture-', 'Reçu' => 'Recu-'][$title] ?? 'Devis-';
        return $prefix.(preg_replace('/[^A-Za-z0-9-]/', '', $m->document_snapshot['number'] ?? '') ?: $m->id).'.pdf';
    }

    private function day(mixed $value): ?string
    {
        if (!$value) return null;
        try { return preg_match('#^\d{2}/\d{2}/\d{4}$#', (string) $value) ? (string) $value : Carbon::parse($value)->format('d/m/Y'); }
        catch (\Throwable) { return null; }
    }
}

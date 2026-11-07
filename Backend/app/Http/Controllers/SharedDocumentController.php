<?php
namespace App\Http\Controllers;
use App\Models\CommercialMessage;
use App\Services\Audit;

/** Serves the archived PDF behind the signed link added to a WhatsApp message. */
class SharedDocumentController
{
    public function __invoke(CommercialMessage $message)
    {
        abort_unless($message->channel==='whatsapp' && $message->direction==='outgoing' && in_array($message->status,['draft','shared']) && $message->archive, 404);
        abort_if($message->contact?->archived_at, 404);
        Audit::record('document_whatsapp_consulte', $message);
        $name=($message->invoice_id ? 'Facture-' : 'Devis-').(preg_replace('/[^A-Za-z0-9-]/','',$message->document_snapshot['number'] ?? '') ?: $message->id).'.pdf';
        return response($message->archive->contents())->withHeaders([
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'inline; filename="'.$name.'"',
            'Cache-Control'=>'private, no-store',
            'X-Robots-Tag'=>'noindex, nofollow',
            'Referrer-Policy'=>'no-referrer',
        ]);
    }
}

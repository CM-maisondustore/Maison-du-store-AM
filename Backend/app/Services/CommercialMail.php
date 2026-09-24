<?php
namespace App\Services;
use App\Models\{CommercialMessage, Contact};
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Validation\ValidationException;
use Webklex\PHPIMAP\ClientManager;
class CommercialMail
{
    public function __construct(private ClientManager $clients) {}
    public function configured(): bool
    {
        return config('workspace.mail_enabled') && config('mail.default') === 'smtp' && (bool) config('mail.mailers.smtp.password');
    }
    public function send(CommercialMessage $message): void
    {
        if (!$this->configured()) throw ValidationException::withMessages(['mail' => 'La connexion Hostinger Email doit être activée dans la configuration privée.']);
        // Claim once before contacting SMTP: double clicks cannot send twice.
        $claimed = DB::transaction(function () use ($message) {
            $m = CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($m->direction === 'outgoing' && $m->status === 'draft', 409, 'Ce message a déjà été envoyé ou son envoi est en cours de vérification.');
            if ($m->kind === 'promotion' && (!$m->contact?->marketing_consent_at || $m->contact->archived_at || mb_strtolower($m->recipient) !== mb_strtolower($m->contact->email ?? ''))) throw ValidationException::withMessages(['mail' => 'Le destinataire doit avoir un consentement commercial actif.']);
            if ($m->quote_id && !in_array($m->quote->status, ['approved', 'accepted'])) throw ValidationException::withMessages(['mail' => 'Validez le devis avant envoi.']);
            if ($m->invoice_id && !$m->invoice->issued_at) throw ValidationException::withMessages(['mail' => 'Émettez la facture avant envoi.']);
            $documents = app(Documents::class);
            $snapshot = $m->invoice_id ? $documents->invoiceData($m->invoice) : ($m->quote_id ? $documents->quoteData($m->quote) : null);
            $m->update(['status' => 'sending', 'document_snapshot' => $snapshot]); return $m;
        });
        try {
            $pdf = $claimed->document_snapshot ? app(Documents::class)->render($claimed->document_snapshot) : null;
            Mail::mailer('smtp')->raw($claimed->body, function ($mail) use ($claimed, $pdf) {
                $mail->to($claimed->recipient)->subject($claimed->subject)->replyTo(config('mail.from.address'));
                if ($pdf) $mail->attachData($pdf, $claimed->invoice_id ? 'facture.pdf' : 'devis.pdf', ['mime' => 'application/pdf']);
            });
            $claimed->update(['status' => 'sent', 'sent_at' => now()]); Audit::record('message_envoye', $claimed);
        } catch (\Throwable $e) {
            // SMTP may have accepted the message even if the final response was lost.
            $claimed->update(['status' => 'uncertain']);
            throw ValidationException::withMessages(['mail' => 'Envoi non confirmé. Vérifiez la boîte Hostinger avant de préparer un nouvel envoi pour éviter un doublon.']);
        }
    }
    public function sync(int $page = 1): int
    {
        if (!$this->configured()) throw ValidationException::withMessages(['mail' => 'Renseignez le mot de passe Hostinger Email et activez la messagerie.']);
        $client = $this->clients->make(['host' => config('workspace.imap_host'), 'port' => config('workspace.imap_port'), 'encryption' => 'ssl', 'validate_cert' => true, 'username' => config('mail.mailers.smtp.username'), 'password' => config('mail.mailers.smtp.password'), 'protocol' => 'imap', 'timeout' => 15]);
        try {
            $client->connect(); $folder = $client->getFolderByPath('INBOX');
            $status = array_change_key_case($folder->examine(), CASE_LOWER);
            $validity = $status['uidvalidity'] ?? throw new \RuntimeException('Missing mailbox identity');
            $messages = $folder->query()->all()->where('CUSTOM SMALLER', 1048576)->leaveUnread()->setFetchOrderDesc()->limit(20, $page)->get();
            $count = 0;
            foreach ($messages as $mail) {
                $sender = mb_strtolower((string) ($mail->getFrom()->first()?->mail ?? ''));
                $key = hash('sha256', config('mail.mailers.smtp.username').'|INBOX|'.$validity.'|'.$mail->getUid());
                if (CommercialMessage::where('remote_key', $key)->exists()) continue;
                $body = $mail->getTextBody() ?: html_entity_decode(strip_tags($mail->getHTMLBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                try { $receivedAt = $mail->getDate()->toDate(); } catch (\Throwable $e) { $receivedAt = now(); }
                $message = CommercialMessage::firstOrCreate(['remote_key' => $key], ['direction' => 'incoming', 'status' => 'received', 'sender' => mb_substr($sender, 0, 250), 'recipient' => config('mail.from.address'), 'subject' => mb_substr((string) $mail->getSubject(), 0, 250), 'body' => mb_substr($body, 0, 200000), 'received_at' => $receivedAt, 'contact_id' => Contact::where('email', $sender)->value('id')]);
                if ($message->wasRecentlyCreated) $count++;
            }
            return $count;
        } catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) { throw ValidationException::withMessages(['mail' => 'Synchronisation indisponible. Vérifiez les paramètres IMAP et le mot de passe dans le fichier privé.']); }
        finally { try { $client->disconnect(); } catch (\Throwable $e) {} }
    }
}

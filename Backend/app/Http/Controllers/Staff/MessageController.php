<?php
namespace App\Http\Controllers\Staff;
use App\Models\{CommercialMessage, Contact, Quote, Appointment, Invoice};
use App\Services\{Audit, CommercialMail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, URL};
use Illuminate\Validation\ValidationException;
class MessageController
{
    public const SHARED_LINK_DAYS = 30;
    public function index(Request $r, CommercialMail $mail)
    {
        $state = \App\Services\Trash::state($r);
        $query = CommercialMessage::with('contact')->listState($state)->latest();
        if (in_array($r->input('direction'), ['incoming', 'outgoing'])) $query->where('direction', $r->input('direction'));
        return view('staff.messages', ['state' => $state, 'messages' => \App\Services\Trash::order($query, $state)->paginate(25)->withQueryString(), 'configured' => $mail->configured()]);
    }
    public function create(Request $r)
    {
        $r->validate(['channel'=>'sometimes|in:email,whatsapp','template_key'=>'sometimes|in:'.implode(',',array_keys(\App\Services\CommunicationTemplates::LABELS))]);
        $draft = ['recipient' => '', 'subject' => '', 'body' => '', 'kind' => 'commercial'];
        if ($r->filled('contact_id')) { $contact = Contact::findOrFail($r->input('contact_id')); $draft['contact_id'] = $contact->id; $draft['recipient'] = $contact->email ?? ''; }
        if ($r->filled('reply')) { $message = CommercialMessage::findOrFail($r->input('reply')); $draft = [...$draft, 'recipient' => $message->sender, 'subject' => 'Re: '.$message->subject, 'contact_id' => $message->contact_id]; }
        if ($r->filled('quote_id')) { $quote = Quote::findOrFail($r->input('quote_id')); $draft = [...$draft, 'contact_id' => $quote->contact_id, 'quote_id' => $quote->id, 'recipient' => $quote->contact['email'] ?? '', 'kind' => 'devis', 'subject' => 'Votre devis Maison du Store AM', 'body' => "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe. Nous restons disponibles pour toute précision.\n\nMaison du Store AM"]; }
        if ($r->filled('appointment_id')) { $appointment = Appointment::findOrFail($r->input('appointment_id')); $draft = [...$draft, 'contact_id' => $appointment->contact_id, 'appointment_id' => $appointment->id, 'recipient' => $appointment->details['email'] ?? '', 'kind' => 'rendez_vous', 'subject' => 'Votre rendez-vous Maison du Store AM', 'body' => "Bonjour,\n\nVotre rendez-vous du {$appointment->date} à {$appointment->time} (heure d’Abidjan) est ".($appointment->status === 'confirmed' ? 'confirmé.' : ($appointment->status === 'cancelled' ? 'annulé.' : 'en attente de confirmation.'))."\n\nMaison du Store AM"]; }
        if ($r->filled('invoice_id')) { $invoice = Invoice::findOrFail($r->input('invoice_id')); $draft = [...$draft, 'contact_id' => $invoice->contact_id, 'invoice_id' => $invoice->id, 'recipient' => $invoice->snapshot['customer']['email'] ?? '', 'kind' => 'facture', 'subject' => 'Votre facture Maison du Store AM', 'body' => "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe.\n\nMaison du Store AM"]; }
        $channel=$r->filled('reply') ? 'email' : $r->input('channel','email');
        $record=!empty($draft['quote_id']) ? Quote::findOrFail($draft['quote_id']) : (!empty($draft['invoice_id']) ? Invoice::findOrFail($draft['invoice_id']) : (!empty($draft['appointment_id']) ? Appointment::findOrFail($draft['appointment_id']) : null));
        $contact=!empty($draft['contact_id']) ? Contact::find($draft['contact_id']) : null;
        $key=$r->input('template_key',match($draft['kind']){'devis'=>'quote','facture'=>'invoice','rendez_vous'=>'appointment',default=>'commercial'});
        if (!$r->filled('reply')) $draft=[...$draft,...\App\Services\CommunicationTemplates::draft($key,\App\Services\CommunicationTemplates::context($record,$contact),$channel)];
        if (!$record && $key==='promotion') $draft['kind']='promotion';
        $c=$record instanceof Quote ? $record->contact : ($record instanceof Invoice ? $record->snapshot['customer'] : ($record?->details ?? []));
        $draft['recipients']=['email'=>$draft['recipient'],'whatsapp'=>$c['phone'] ?? $contact?->phone ?? ''];
        $draft['recipient']=$draft['recipients'][$channel];
        $draft['channel']=$channel; $draft['template_key']=$key;
        return view('staff.compose', ['draft' => $draft, 'contacts' => Contact::orderBy('name')->get()]);
    }
    public function store(Request $r)
    {
        $r->validate(['channel'=>'sometimes|in:email,whatsapp','template_key'=>'sometimes|in:'.implode(',',array_keys(\App\Services\CommunicationTemplates::LABELS))]);
        $channel=$r->input('channel','email');
        $recipientRules=$channel==='whatsapp' ? ['required','string','regex:/^\+?[1-9][0-9\s()-]{7,24}$/'] : ['required','email','max:200'];
        $data = $r->validate(['recipient' => $recipientRules, 'subject' => 'required|string|max:200|not_regex:/[\r\n]/', 'body' => 'required|string|max:'.($channel==='whatsapp' ? '3000':'30000'), 'kind' => 'required|in:commercial,promotion,devis,rendez_vous,facture', 'contact_id' => 'nullable|exists:contacts,id', 'quote_id' => 'nullable|exists:quotes,id', 'appointment_id' => 'nullable|exists:appointments,id', 'invoice_id' => 'nullable|exists:invoices,id'], ['recipient.required'=>$channel==='whatsapp' ? 'Indiquez le numéro WhatsApp du client avec son indicatif pays.' : 'Indiquez l’adresse e-mail du destinataire.', 'recipient.regex'=>'Indiquez un numéro WhatsApp avec indicatif pays, par exemple +2250700000000.', 'recipient.email'=>'Indiquez une adresse e-mail valide ou choisissez le canal WhatsApp.']);
        $data['channel']=$channel;
        if ($channel==='whatsapp') {
            $data['recipient']=preg_replace('/\D/','',$data['recipient']);
            if (strlen($data['recipient'])>15 || strlen($data['recipient'])<8) throw ValidationException::withMessages(['recipient'=>'Indiquez le numéro avec son indicatif pays.']);
        }
        if (count(array_filter(collect($data)->only(['quote_id', 'invoice_id', 'appointment_id'])->all())) > 1) throw ValidationException::withMessages(['document' => 'Un seul document par message.']);
        foreach (['quote_id' => Quote::class, 'invoice_id' => Invoice::class, 'appointment_id' => Appointment::class] as $key => $model) if (!empty($data[$key])) {
            $record = $model::findOrFail($data[$key]);
            $field=$channel==='whatsapp' ? 'phone':'email';
            $expected = match ($key) { 'quote_id' => $record->contact[$field] ?? '', 'invoice_id' => $record->snapshot['customer'][$field] ?? '', default => $record->details[$field] ?? '' };
            if ($channel==='whatsapp') $expected=preg_replace('/\D/','',$expected);
            if (mb_strtolower($expected) !== mb_strtolower($data['recipient'])) throw ValidationException::withMessages(['recipient' => 'Le destinataire doit correspondre au client du document.']);
            $data['contact_id'] = $record->contact_id;
            $data['source_fingerprint']=app(\App\Services\CommunicationAutomation::class)->fingerprint($record);
        }
        if (empty($data['contact_id']) && $channel==='email') $data['contact_id'] = Contact::where('email', mb_strtolower($data['recipient']))->value('id');
        if ($r->filled('template_key')) {
            $template=\App\Services\CommunicationTemplates::get($channel,$r->input('template_key'));
            $data['template_snapshot']=['id'=>$template->id,'key'=>$template->key,'version'=>$template->version,'channel'=>$channel];
        }
        $message = CommercialMessage::create([...$data, 'sender' => $channel==='whatsapp' ? config('workspace.whatsapp_number') : config('mail.from.address'), 'user_id' => $r->user()->id]);
        Audit::record('brouillon_cree', $message); return redirect()->route('staff.messages.show', $message);
    }
    public function show(CommercialMessage $message) { if ($message->direction === 'incoming' && !$message->read_at) $message->update(['read_at' => now()]); return view('staff.message', ['message' => $message, 'threads' => CommercialMessage::with('contact')->latest()->limit(25)->get()]); }
    public function update(Request $r, CommercialMessage $message)
    {
        $data = $r->validate(['subject' => 'required|string|max:200|not_regex:/[\r\n]/', 'body' => 'required|string|max:'.($message->channel==='whatsapp'?'3000':'30000')]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($message, $data) {
            $locked = CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->direction === 'outgoing' && $locked->status === 'draft', 409);
            $locked->update([...$data,'auto_send'=>false]); Audit::record('brouillon_modifie', $locked);
        });
        return back()->with('success', 'Brouillon modifié.');
    }
    public function attachment(CommercialMessage $message, \App\Services\Documents $documents)
    {
        abort_unless($message->document_snapshot, 404);
        return response($message->archive?->contents() ?? $documents->render($message->document_snapshot))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="document-archive.pdf"');
    }
    public function send(CommercialMessage $message, CommercialMail $mail) { $mail->send($message); return back()->with('success', 'Message accepté par le serveur SMTP Hostinger.'); }
    public function whatsapp(CommercialMessage $message)
    {
        [$recipient,$body]=DB::transaction(function () use($message) {
            $m=CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($m->channel==='whatsapp' && $m->status==='draft' && $m->direction==='outgoing',409);
            app(\App\Services\CommunicationAutomation::class)->validateMessage($m);
            $body=$m->body;
            if ($this->archiveDocument($m)) {
                $path=URL::temporarySignedRoute('documents.whatsapp',now()->addDays(self::SHARED_LINK_DAYS),['message'=>$m->id],false);
                $body.="\n\nVotre document : ".url($path);
            }
            return [$m->recipient,$body];
        });
        return redirect()->away('https://wa.me/'.preg_replace('/\D/','',$recipient).'?text='.rawurlencode($body));
    }
    public function shared(CommercialMessage $message)
    {
        DB::transaction(function () use($message) {
            $m=CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($m->channel==='whatsapp' && $m->direction==='outgoing' && $m->status==='draft',409);
            app(\App\Services\CommunicationAutomation::class)->validateMessage($m);
            $this->archiveDocument($m);
            $m->update(['status'=>'shared','sent_at'=>now()]); Audit::record('whatsapp_partage_manuellement',$m);
        });
        return back()->with('success','Partage déclaré. La livraison n’est pas vérifiée par WhatsApp.');
    }
    public function cancel(CommercialMessage $message)
    {
        DB::transaction(function () use($message) {
            $locked=CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->direction==='outgoing' && $locked->status==='draft',409);
            $locked->update(['status'=>'cancelled','auto_send'=>false]); Audit::record('brouillon_annule',$locked);
        });
        return back()->with('success','Brouillon annulé et conservé dans l’historique.');
    }
    public function sync(Request $r, CommercialMail $mail) { $data = $r->validate(['page' => 'required|integer|min:1|max:1000']); $count = $mail->sync($data['page']); return back()->with('success', "$count nouveau(x) message(s) importé(s)."); }
    /** Freeze the exact PDF of a WhatsApp message once, so the shared link and the archive always match. */
    private function archiveDocument(CommercialMessage $m): bool
    {
        if ($m->archive) return true;
        $documents=app(\App\Services\Documents::class);
        if ($m->invoice_id) {
            $snapshot=$documents->invoiceData($m->invoice); $pdf=$documents->invoice($m->invoice);
        } elseif ($m->quote_id && $m->automation_event!=='quote_received') {
            $issue=\App\Services\DocumentIssues::quote($m->quote,$documents); $snapshot=$issue->snapshot; $pdf=$issue->archive->contents();
        } else return false;
        \App\Models\DocumentArchive::capture($pdf,['commercial_message_id'=>$m->id]);
        $m->update(['document_snapshot'=>$snapshot]); $m->unsetRelation('archive');
        return true;
    }
}

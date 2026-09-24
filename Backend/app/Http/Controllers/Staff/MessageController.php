<?php
namespace App\Http\Controllers\Staff;
use App\Models\{CommercialMessage, Contact, Quote, Appointment, Invoice};
use App\Services\{Audit, CommercialMail};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class MessageController
{
    public function index(Request $r, CommercialMail $mail)
    {
        $query = CommercialMessage::with('contact')->latest();
        if (in_array($r->input('direction'), ['incoming', 'outgoing'])) $query->where('direction', $r->input('direction'));
        return view('staff.messages', ['messages' => $query->paginate(25)->withQueryString(), 'configured' => $mail->configured()]);
    }
    public function create(Request $r)
    {
        $draft = ['recipient' => '', 'subject' => '', 'body' => '', 'kind' => 'commercial'];
        if ($r->filled('contact_id')) { $contact = Contact::findOrFail($r->input('contact_id')); $draft['contact_id'] = $contact->id; $draft['recipient'] = $contact->email ?? ''; }
        if ($r->filled('reply')) { $message = CommercialMessage::findOrFail($r->input('reply')); $draft = [...$draft, 'recipient' => $message->sender, 'subject' => 'Re: '.$message->subject, 'contact_id' => $message->contact_id]; }
        if ($r->filled('quote_id')) { $quote = Quote::findOrFail($r->input('quote_id')); $draft = [...$draft, 'contact_id' => $quote->contact_id, 'quote_id' => $quote->id, 'recipient' => $quote->contact['email'] ?? '', 'kind' => 'devis', 'subject' => 'Votre devis Maison du Store AM', 'body' => "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe. Nous restons disponibles pour toute précision.\n\nMaison du Store AM"]; }
        if ($r->filled('appointment_id')) { $appointment = Appointment::findOrFail($r->input('appointment_id')); $draft = [...$draft, 'contact_id' => $appointment->contact_id, 'appointment_id' => $appointment->id, 'recipient' => $appointment->details['email'] ?? '', 'kind' => 'rendez_vous', 'subject' => 'Votre rendez-vous Maison du Store AM', 'body' => "Bonjour,\n\nVotre rendez-vous du {$appointment->date} à {$appointment->time} (heure d’Abidjan) est ".($appointment->status === 'confirmed' ? 'confirmé.' : ($appointment->status === 'cancelled' ? 'annulé.' : 'en attente de confirmation.'))."\n\nMaison du Store AM"]; }
        if ($r->filled('invoice_id')) { $invoice = Invoice::findOrFail($r->input('invoice_id')); $draft = [...$draft, 'contact_id' => $invoice->contact_id, 'invoice_id' => $invoice->id, 'recipient' => $invoice->snapshot['customer']['email'] ?? '', 'kind' => 'facture', 'subject' => 'Votre facture Maison du Store AM', 'body' => "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe.\n\nMaison du Store AM"]; }
        return view('staff.compose', ['draft' => $draft, 'contacts' => Contact::whereNotNull('email')->orderBy('name')->get()]);
    }
    public function store(Request $r)
    {
        $data = $r->validate(['recipient' => 'required|email|max:200', 'subject' => 'required|string|max:200|not_regex:/[\r\n]/', 'body' => 'required|string|max:30000', 'kind' => 'required|in:commercial,promotion,devis,rendez_vous,facture', 'contact_id' => 'nullable|exists:contacts,id', 'quote_id' => 'nullable|exists:quotes,id', 'appointment_id' => 'nullable|exists:appointments,id', 'invoice_id' => 'nullable|exists:invoices,id']);
        if (count(array_filter(collect($data)->only(['quote_id', 'invoice_id', 'appointment_id'])->all())) > 1) throw ValidationException::withMessages(['document' => 'Un seul document par message.']);
        foreach (['quote_id' => Quote::class, 'invoice_id' => Invoice::class, 'appointment_id' => Appointment::class] as $key => $model) if (!empty($data[$key])) {
            $record = $model::findOrFail($data[$key]);
            $expected = match ($key) { 'quote_id' => $record->contact['email'] ?? '', 'invoice_id' => $record->snapshot['customer']['email'] ?? '', default => $record->details['email'] ?? '' };
            if (mb_strtolower($expected) !== mb_strtolower($data['recipient'])) throw ValidationException::withMessages(['recipient' => 'Le destinataire doit correspondre au client du document.']);
            $data['contact_id'] = $record->contact_id;
        }
        if (empty($data['contact_id'])) $data['contact_id'] = Contact::where('email', mb_strtolower($data['recipient']))->value('id');
        $message = CommercialMessage::create([...$data, 'sender' => config('mail.from.address'), 'user_id' => $r->user()->id]);
        Audit::record('brouillon_cree', $message); return redirect()->route('staff.messages.show', $message);
    }
    public function show(CommercialMessage $message) { if ($message->direction === 'incoming' && !$message->read_at) $message->update(['read_at' => now()]); return view('staff.message', compact('message')); }
    public function update(Request $r, CommercialMessage $message)
    {
        $data = $r->validate(['subject' => 'required|string|max:200|not_regex:/[\r\n]/', 'body' => 'required|string|max:30000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($message, $data) {
            $locked = CommercialMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->direction === 'outgoing' && $locked->status === 'draft', 409);
            $locked->update($data); Audit::record('brouillon_modifie', $locked);
        });
        return back()->with('success', 'Brouillon modifié.');
    }
    public function attachment(CommercialMessage $message, \App\Services\Documents $documents)
    {
        abort_unless($message->document_snapshot, 404);
        return response($documents->render($message->document_snapshot))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="document-archive.pdf"');
    }
    public function send(CommercialMessage $message, CommercialMail $mail) { $mail->send($message); return back()->with('success', 'Message accepté par le serveur SMTP Hostinger.'); }
    public function sync(Request $r, CommercialMail $mail) { $data = $r->validate(['page' => 'required|integer|min:1|max:1000']); $count = $mail->sync($data['page']); return back()->with('success', "$count nouveau(x) message(s) importé(s)."); }
}

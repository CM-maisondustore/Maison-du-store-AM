<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Quote, Appointment, Intervention, Contact, CommercialMessage, CommunicationCheck, Invoice};
use App\Services\MailConnection;
use App\Services\Quotes\QuoteFinancials;
use Illuminate\Http\Request;
class DashboardController
{
    public function __invoke(Request $r, MailConnection $mail)
    {
        return $r->user()->isAdmin() ? $this->admin($mail) : $this->agent($r);
    }
    private function admin(MailConnection $mail)
    {
        $jobs = Intervention::with(['contact', 'agent'])->whereNotIn('status', ['completed', 'cancelled'])->orderBy('scheduled_at')->limit(10)->get();
        $quoteCount = Quote::unarchived()->whereIn('status', ['pending', 'draft'])->count();
        $oldest = Quote::unarchived()->whereIn('status', ['pending', 'draft'])->oldest()->value('created_at');
        $appointmentCount = Appointment::unarchived()->where('status', 'pending')->count();
        $nextAppointment = Appointment::where('status', 'pending')->where('date', '>=', today()->toDateString())->orderBy('date')->orderBy('time')->first();
        $clientCount = Contact::where('kind', 'client')->count();
        $newClients = Contact::where('kind', 'client')->where('created_at', '>=', now()->startOfMonth())->count();
        $unread = CommercialMessage::unarchived()->where('direction', 'incoming')->whereNull('read_at')->count();
        $queues = [
            ['count' => $quoteCount, 'label' => 'devis à traiter', 'detail' => $oldest ? 'Le plus ancien attend depuis '.\Illuminate\Support\Carbon::parse($oldest)->locale('fr')->diffForHumans(null, true).'.' : 'Aucun devis en attente.', 'cta' => 'Ouvrir la liste filtrée', 'href' => route('staff.quotes.index', ['status' => 'pending'])],
            ['count' => $appointmentCount, 'label' => 'rendez-vous à confirmer', 'detail' => $nextAppointment ? 'Le prochain : '.\Illuminate\Support\Carbon::parse($nextAppointment->date)->format('d/m').' à '.$nextAppointment->time.'.' : 'Aucun rendez-vous à confirmer.', 'cta' => 'Confirmer les rendez-vous', 'href' => route('staff.appointments.index', ['vue' => 'liste', 'status' => 'pending'])],
            ['count' => $clientCount, 'label' => 'dossiers clients', 'detail' => $newClients.' nouveau(x) client(s) ce mois-ci.', 'cta' => 'Ouvrir les clients', 'href' => route('staff.contacts.index')],
            ['count' => $unread, 'label' => 'messages non lus', 'detail' => $unread ? 'À traiter dans la messagerie.' : 'Boîte à jour.', 'cta' => 'Ouvrir la messagerie', 'href' => route('staff.messages.index', ['direction' => 'incoming'])],
        ];
        $weekStart = today()->startOfWeek();
        $weekJobs = Intervention::with(['contact', 'agent'])->whereBetween('scheduled_at', [$weekStart, $weekStart->copy()->addDays(6)->endOfDay()])->where('status', '!=', 'cancelled')->orderBy('scheduled_at')->get();
        $week = [];
        for ($i = 0; $i < 6; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $week[] = ['date' => $date, 'events' => $weekJobs->filter(fn ($job) => $job->scheduled_at->isSameDay($date))->map(fn ($job) => ['time' => $job->scheduled_at->format('H:i'), 'title' => \App\Services\StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind, 'client' => $job->contact->name, 'agent' => $job->agent->name, 'tone' => ['devis_sur_place' => 'info', 'sav' => 'error'][$job->kind] ?? 'progress', 'href' => route('staff.interventions.show', $job)])->values()];
        }
        $toValidate = Quote::unarchived()->whereIn('status', ['pending', 'draft'])->latest()->limit(3)->get()->map(function ($quote) {
            $financial = QuoteFinancials::forQuote($quote);
            return ['quote' => $quote, 'total' => (int) ($financial['total'] ?? 0), 'pending' => !empty($quote->configuration['calculation']['pending']) || empty($quote->lines)];
        });
        $unpaid = Invoice::with('contact')->where('status', 'issued')->latest('issued_at')->limit(5)->get()->map(fn ($invoice) => ['invoice' => $invoice, 'total' => (int) (QuoteFinancials::forSnapshot($invoice->snapshot)['total'] ?? 0)]);
        return view('staff.dashboard', ['jobs' => $jobs, 'queues' => $queues, 'week' => $week, 'weekStart' => $weekStart, 'toValidate' => $toValidate, 'unpaid' => $unpaid, 'smtpVerified' => $mail->verified(), 'lastRun' => CommunicationCheck::where('key', 'scheduler')->first()]);
    }
    private function agent(Request $r)
    {
        $id = $r->user()->id;
        $open = Intervention::with('contact')->where('agent_id', $id)->whereNotIn('status', ['completed', 'cancelled'])->orderBy('scheduled_at');
        $next = (clone $open)->where(fn ($q) => $q->where('status', 'in_progress')->orWhere('scheduled_at', '>=', today()))->orderByRaw("case when status='in_progress' then 0 else 1 end")->first();
        return view('staff.dashboard', [
            'next' => $next,
            'todayJobs' => (clone $open)->whereDate('scheduled_at', today())->get(),
            'weekJobs' => (clone $open)->where('scheduled_at', '>=', today()->addDay())->where('scheduled_at', '<=', today()->endOfWeek())->get(),
            'appointments' => Appointment::where('agent_id', $id)->whereIn('status', ['pending', 'confirmed'])->where('date', '>=', today()->toDateString())->orderBy('date')->orderBy('time')->limit(5)->get(),
        ]);
    }
}

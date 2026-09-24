<?php
namespace App\Http\Controllers\Staff;
use App\Models\{Quote, Appointment, Intervention, Contact, CommercialMessage};
use Illuminate\Http\Request;
class DashboardController
{
    public function __invoke(Request $r)
    {
        $jobs = Intervention::with(['contact', 'agent'])->whereNotIn('status', ['completed', 'cancelled'])->orderBy('scheduled_at');
        if (!$r->user()->isAdmin()) $jobs->where('agent_id', $r->user()->id);
        return view('staff.dashboard', [
            'jobs' => $jobs->limit(10)->get(),
            'counts' => $r->user()->isAdmin() ? ['Devis à traiter' => Quote::whereIn('status', ['pending', 'draft'])->count(), 'Rendez-vous à confirmer' => Appointment::where('status', 'pending')->count(), 'Dossiers clients' => Contact::where('kind', 'client')->count(), 'Messages reçus non lus' => CommercialMessage::where('direction', 'incoming')->whereNull('read_at')->count()] : [],
        ]);
    }
}

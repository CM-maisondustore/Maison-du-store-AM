@extends('staff.layout')
@section('title', auth()->user()->isAdmin() ? 'Vue d’ensemble' : 'Aujourd’hui')
@if(!auth()->user()->isAdmin())
@section('fab')<a class="button fab" href="{{ route('staff.quotes.create') }}">@include('staff.partials.icon',['n'=>'plus','s'=>18])Devis sur place</a>@endsection
@section('page-actions')<a class="button desktop-only" href="{{ route('staff.quotes.create') }}">Devis sur place</a>@endsection
@endif
@section('subtitle', ucfirst(now()->locale('fr')->isoFormat('dddd')).' '.now()->format('d/m/Y').(auth()->user()->isAdmin() ? '' : ', '.auth()->user()->name))
@section('content')
@if(auth()->user()->isAdmin())
<nav class="queues" aria-label="Files d’attente">
@foreach($queues as $queue)
<a class="queue" href="{{ $queue['href'] }}"><span><strong>{{ $queue['count'] }}</strong><b>{{ $queue['label'] }}</b></span><small>{{ $queue['detail'] }}</small><em>{{ $queue['cta'] }}</em></a>
@endforeach
</nav>
<div class="with-aside even">
<div>
<section class="panel flush" aria-labelledby="planning-title">
<div class="panel-head"><h2 id="planning-title">Planning de la semaine <span class="muted">du {{ $weekStart->format('d/m') }} au {{ $weekStart->copy()->addDays(5)->format('d/m/Y') }}</span></h2><a class="link" href="{{ route('staff.appointments.index') }}">Ouvrir le calendrier</a></div>
<div class="week">
@foreach($week as $day)
<div class="day {{ $day['date']->isToday() ? 'today' : '' }}"><h3>{{ $day['date']->locale('fr')->isoFormat('ddd') }} {{ $day['date']->format('d/m') }}</h3>
@forelse($day['events'] as $event)
<a class="event ev-{{ $event['tone'] }}" href="{{ $event['href'] }}"><time>{{ $event['time'] }}</time><strong>{{ $event['title'] }}</strong>{{ $event['client'] }}<br><span class="muted">{{ $event['agent'] }}</span></a>
@empty<p class="muted">Aucune intervention</p>@endforelse
</div>
@endforeach
</div>
</section>
<section class="panel flush" aria-labelledby="presta-title">
<div class="panel-head"><h2 id="presta-title">Prestations à venir</h2><a class="link" href="{{ route('staff.interventions.index') }}">Toutes les prestations</a></div>
@include('staff.jobs-table')
</section>
</div>
<aside>
<section class="panel flush" aria-labelledby="valid-title">
<div class="panel-head"><h2 id="valid-title">Devis à valider</h2></div>
<ul class="rows">
@forelse($toValidate as $row)
<li><a class="row" href="{{ route('staff.quotes.show',$row['quote']) }}"><span><strong>{{ \App\Services\StaffInterface::quoteClient($row['quote']) }}</strong><span class="ref">{{ $row['quote']->reference }}</span></span>
<span class="end">@if($row['pending'])<span class="undefined">Prix à définir</span>@else<span class="amount">{{ \App\Services\StaffInterface::money($row['total']) }}</span>@endif<br><span class="muted">depuis {{ $row['quote']->created_at->locale('fr')->diffForHumans(null,true) }}</span></span></a></li>
@empty<li class="empty">Aucun devis en attente.</li>@endforelse
</ul>
<div class="panel-foot"><a class="link" href="{{ route('staff.quotes.index',['status'=>'draft']) }}">Voir les devis à valider</a></div>
</section>
<section class="panel flush" aria-labelledby="fact-title">
<div class="panel-head"><h2 id="fact-title">Factures émises non payées</h2></div>
<ul class="rows">
@forelse($unpaid as $row)
<li><a class="row" href="{{ route('staff.invoices.show',$row['invoice']) }}"><span><strong>{{ $row['invoice']->contact->name }}</strong><span class="ref">{{ $row['invoice']->number }}</span></span>
<span class="end"><span class="amount">{{ \App\Services\StaffInterface::money($row['total']) }}</span><br><span class="muted">Émise le {{ $row['invoice']->issued_at?->format('d/m/Y') }}</span></span></a></li>
@empty<li class="empty">Aucune facture en attente de paiement.</li>@endforelse
</ul>
@if($unpaid->isNotEmpty())<div class="panel-foot row d-flex between"><strong>Total dû</strong><span class="amount">{{ \App\Services\StaffInterface::money($unpaid->sum('total')) }}</span></div>@endif
</section>
<section class="panel" aria-labelledby="auto-title">
<div class="title-row between"><h2 id="auto-title">Automatisations</h2>
@if($smtpVerified)<span class="badge st-ok"><span aria-hidden="true">✓</span>SMTP vérifié</span>@else<span class="badge st-warn"><span aria-hidden="true">!</span>SMTP à vérifier</span>@endif</div>
<p>{{ $lastRun?->checked_at ? 'Dernier passage le '.$lastRun->checked_at->format('d/m/Y à H:i').'.' : 'En attente du premier passage.' }}</p>
@if($lastRun && !$lastRun->successful)<p class="notice">Le dernier traitement a échoué. Vérifiez la planification.</p>@endif
<div class="actions mb-0"><form method="post" action="{{ route('staff.communications.process') }}" data-confirm="Exécuter les règles actives ? Les règles en mode automatique peuvent envoyer des e-mails aux clients.">@csrf<button class="secondary">Exécuter maintenant</button></form><a class="link" href="{{ route('staff.communications.index') }}">Voir les règles</a></div>
</section>
</aside>
</div>
@else
@if($next)
<section class="hero-job" aria-labelledby="next-t">
<p class="muted">Prochaine intervention{{ $todayJobs->count() ? ', '.$todayJobs->count().' aujourd’hui' : '' }}</p>
<div class="big-time">{{ $next->scheduled_at->isToday() ? $next->scheduled_at->format('H:i') : $next->scheduled_at->format('d/m H:i') }}</div>
<p class="mt-8 m-x0">{{ \App\Services\StaffInterface::JOB_KINDS[$next->kind] ?? $next->kind }}</p>
<h2 id="next-t">{{ $next->contact->name }}</h2>
<p class="prose">{{ $next->address }}</p>
<div class="contact-actions">
<a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($next->address) }}" target="_blank" rel="noopener">@include('staff.partials.icon',['n'=>'map'])Itinéraire</a>
@if($next->contact->phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$next->contact->phone) }}">@include('staff.partials.icon',['n'=>'phone'])Appeler</a>@endif
</div>
@if($next->instructions)<div class="consignes"><strong>Consignes</strong><p class="prose mt-4 m-x0">{{ $next->instructions }}</p></div>@endif
<div class="actions mb-0"><a class="button block" href="{{ route('staff.interventions.show',$next) }}">Ouvrir la fiche et démarrer</a></div>
</section>
@else
<div class="panel empty">Aucune intervention à venir. Le bureau vous préviendra dès qu’une prestation vous sera affectée.</div>
@endif
<section class="panel flush" aria-labelledby="day-t">
<div class="panel-head"><h2 id="day-t">Ensuite aujourd’hui</h2></div>
<ul class="rows">
@forelse($todayJobs->reject(fn($job)=>$job->id===$next?->id) as $job)
<li><a class="row" href="{{ route('staff.interventions.show',$job) }}"><span class="amount">{{ $job->scheduled_at->format('H:i') }}</span><span class="grow"><strong>{{ $job->contact->name }}</strong><span class="muted">{{ \App\Services\StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind }}</span></span>@include('staff.status',['status'=>$job->status])</a></li>
@empty<li class="empty">Rien d’autre aujourd’hui.</li>@endforelse
</ul>
</section>
<section class="panel flush" aria-labelledby="week-t">
<div class="panel-head"><h2 id="week-t">Cette semaine</h2></div>
<ul class="rows">
@forelse($weekJobs as $job)
<li><a class="row" href="{{ route('staff.interventions.show',$job) }}"><span class="amount">{{ $job->scheduled_at->format('d/m H:i') }}</span><span class="grow"><strong>{{ $job->contact->name }}</strong><span class="muted">{{ \App\Services\StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind }}</span></span></a></li>
@empty<li class="empty">Pas d’autre intervention cette semaine.</li>@endforelse
</ul>
</section>
@if($appointments->isNotEmpty())
<section class="panel flush" aria-labelledby="rdv-t">
<div class="panel-head"><h2 id="rdv-t">Mes prochains rendez-vous</h2></div>
<ul class="rows">@foreach($appointments as $appointment)<li><a class="row" href="{{ route('staff.appointments.show',$appointment) }}"><span class="amount">{{ \Illuminate\Support\Carbon::parse($appointment->date)->format('d/m') }} {{ $appointment->time }}</span><span class="grow"><strong>{{ trim(($appointment->details['firstName'] ?? '').' '.($appointment->details['lastName'] ?? '')) }}</strong></span>@include('staff.status',['status'=>$appointment->status])</a></li>@endforeach</ul>
</section>
@endif
@endif
@endsection

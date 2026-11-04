@extends('staff.layout')
@section('title', auth()->user()->isAdmin() ? 'Rendez-vous' : 'Mes rendez-vous')
@section('subtitle', $view==='liste' ? 'Tous les rendez-vous' : ($view==='jour' ? ucfirst($start->locale('fr')->isoFormat('dddd')).' '.$start->format('d/m/Y') : 'Semaine du '.$start->format('d/m').' au '.$start->copy()->addDays(5)->format('d/m/Y')))
@section('page-actions')
@if($view!=='liste')
@php($step=$view==='jour' ? 1 : 7)
<a class="button quiet icon-btn" href="{{ route('staff.appointments.index',['vue'=>$view,'date'=>$start->copy()->subDays($step)->format('Y-m-d')]) }}" aria-label="Période précédente">@include('staff.partials.icon',['n'=>'chevron-left','s'=>18])</a>
<a class="button quiet" href="{{ route('staff.appointments.index',['vue'=>$view]) }}">Aujourd’hui</a>
<a class="button quiet icon-btn" href="{{ route('staff.appointments.index',['vue'=>$view,'date'=>$start->copy()->addDays($step)->format('Y-m-d')]) }}" aria-label="Période suivante">@include('staff.partials.icon',['n'=>'chevron-right','s'=>18])</a>
@endif
@endsection
@section('content')
<nav class="chips" aria-label="Affichage">
@foreach(['semaine'=>'Semaine','jour'=>'Jour','liste'=>'Liste'] as $key=>$label)<a class="chip" href="{{ route('staff.appointments.index',['vue'=>$key]) }}" @if($view===$key) aria-current="true" @endif>{{ $label }}</a>@endforeach
@if($view==='liste')
<a class="chip" href="{{ route('staff.appointments.index',['vue'=>'liste','status'=>'pending']) }}" @if(request('status')==='pending') aria-current="true" @endif>À confirmer</a>
<a class="chip" href="{{ route('staff.appointments.index',['vue'=>'liste','status'=>'confirmed']) }}" @if(request('status')==='confirmed') aria-current="true" @endif>Confirmés</a>
@endif
</nav>
<section class="panel flush" aria-label="Calendrier">
@if($view==='liste')
<div class="table-wrap"><table class="cards">
<thead><tr><th scope="col">Date / heure d’Abidjan</th><th scope="col">Client</th><th scope="col">Agent</th><th scope="col">État</th></tr></thead>
<tbody>
@forelse($appointments as $appointment)<tr>
<td class="primary"><a class="row-link mono" href="{{ route('staff.appointments.show',$appointment) }}">{{ \Illuminate\Support\Carbon::parse($appointment->date)->format('d/m/Y') }} · {{ $appointment->time }}</a></td>
<td data-label="Client">{{ $appointment->details['firstName'] ?? '' }} {{ $appointment->details['lastName'] ?? '' }}<small>{{ $appointment->details['appointmentType'] ?? '' }}</small></td>
<td data-label="Agent">{{ $appointment->agent?->name ?? 'Non affecté' }}</td>
<td class="state">@include('staff.status',['status'=>$appointment->status])</td>
</tr>
@empty<tr><td colspan="4" class="empty">Aucun rendez-vous.</td></tr>
@endforelse</tbody>
</table></div>
@include('staff.pagination',['paginator'=>$appointments])
@else
<div class="{{ $view==='semaine' ? 'week' : '' }}">
@foreach($days as $day)
<div class="day {{ $day['date']->isToday() ? 'today' : '' }}"><h3>{{ ucfirst($day['date']->locale('fr')->isoFormat('ddd')) }} {{ $day['date']->format('d/m') }}</h3>
@forelse($day['events'] as $appointment)
@php($badge=\App\Services\StaffInterface::status($appointment->status))
<a class="event ev-{{ ['warn'=>'warn','ok'=>'ok','neutral'=>'neutral'][$badge['tone']] ?? 'info' }}" href="{{ route('staff.appointments.show',$appointment) }}"><time>{{ $appointment->time }}</time><strong>{{ trim(($appointment->details['firstName'] ?? '').' '.($appointment->details['lastName'] ?? '')) ?: 'Client' }}</strong>{{ $appointment->details['appointmentType'] ?? '' }}<br><span aria-hidden="true">{{ $badge['glyph'] }}</span> {{ $badge['label'] }}<br><span class="muted">{{ $appointment->agent?->name ?? 'Non affecté' }}</span></a>
@empty<p class="muted">Aucun rendez-vous</p>@endforelse
</div>
@endforeach
</div>
@endif
</section>
@endsection

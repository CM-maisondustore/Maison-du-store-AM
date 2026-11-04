@extends('staff.layout')
@section('title','Notifications')
@section('subtitle','Les alertes restent conservées dans votre espace professionnel, même si les notifications du navigateur sont désactivées.')
@php($rows=$notifications->getCollection()->map(fn($notification)=>\App\Services\StaffInterface::notification($notification)))
@section('content')
@if($rows->isEmpty())
<section class="panel notif-empty">
<span class="bubble-c tone-neutral" aria-hidden="true">@include('staff.partials.icon',['n'=>'bell','s'=>30])</span>
<h2>Aucune notification pour le moment.</h2>
<p class="muted">Les nouvelles demandes et les prestations affectées apparaîtront ici, en bulle dès leur arrivée.</p>
</section>
@else
<section class="notif-stage" aria-labelledby="notif-recent">
<div class="panel-head">
<h2 id="notif-recent">En ce moment</h2>
<button type="button" class="quiet icon-btn" data-bubbles-shuffle aria-label="Mélanger les bulles" title="Mélanger">@include('staff.partials.icon',['n'=>'shuffle','s'=>18])</button>
</div>
<div class="bubbles" data-bubbles>
@foreach($rows->take(6) as $row)
<a class="bubble" @if($row['url']) href="{{ $row['url'] }}" @else href="{{ route('staff.notifications.index') }}" @endif data-bubble>
<span class="bubble-c tone-{{ $row['tone'] }} {{ $row['unread'] ? 'is-new' : '' }}" aria-hidden="true">@include('staff.partials.icon',['n'=>$row['icon'],'s'=>28])</span>
<span class="bubble-l"><span class="bubble-n">{{ $row['title'] }}</span><span class="bubble-s">{{ $row['ago'] }}</span></span>
</a>
@endforeach
</div>
</section>
<section class="panel flush" aria-labelledby="notif-all">
<div class="panel-head"><h2 id="notif-all">Toutes les alertes</h2></div>
<ul class="rows">
@foreach($rows as $row)
<li class="row notif-row">
<span class="notif-dot tone-{{ $row['tone'] }}" aria-hidden="true">@include('staff.partials.icon',['n'=>$row['icon'],'s'=>18])</span>
<span class="grow"><strong>{{ $row['title'] }}@if($row['unread'])<span class="badge st-progress notif-new">Nouveau</span>@endif</strong>
<span>{{ $row['message'] }}</span><small class="mono muted">{{ $row['time'] }}</small></span>
@if($row['url'])<span class="end"><a class="button quiet" href="{{ $row['url'] }}">Ouvrir</a></span>@endif
</li>
@endforeach
</ul>
@include('staff.pagination',['paginator'=>$notifications])
</section>
@endif
@endsection

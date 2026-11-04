@extends('staff.layout')
@section('title','Notifications')
@section('content')
<section class="panel">
<p>Les alertes restent conservées dans votre espace professionnel, même si les notifications du navigateur sont désactivées.</p>
@forelse($notifications as $notification)
<article class="card" style="margin:1rem 0">
<h2>{{ $notification->data['title'] ?? 'Notification' }}</h2>
<p>{{ $notification->data['message'] ?? '' }}</p>
<small>{{ $notification->created_at?->format('d/m/Y H:i') }}</small>
@if(!empty($notification->data['url']))<p><a class="button secondary" href="{{ $notification->data['url'] }}">Ouvrir</a></p>@endif
</article>
@empty
<p>Aucune notification pour le moment.</p>
@endforelse
@include('staff.pagination',['paginator'=>$notifications])
</section>
@endsection

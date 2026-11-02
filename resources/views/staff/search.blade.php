@extends('staff.layout')
@section('title','Recherche')
@section('content')
<form class="panel search-panel" method="get" action="{{ route('staff.search') }}" role="search">
<label class="grow mt-0">Rechercher<input type="search" name="q" autofocus maxlength="150" value="{{ $q }}" placeholder="{{ auth()->user()->isAdmin() ? 'Client, téléphone, n° de devis ou de facture' : 'Client, n° de devis ou prestation' }}"></label>
<button>Rechercher</button>
</form>
@if($q!=='' && mb_strlen($q)<2)<p class="muted">Saisissez au moins deux caractères.</p>
@elseif($q==='')
<div class="panel empty">Saisissez un nom, un numéro ou un téléphone pour lancer la recherche.</div>
@else
@php($empty=true)
@foreach($groups as $title=>$rows)
@if($rows->isNotEmpty())
@php($empty=false)
<section class="panel flush"><div class="panel-head"><h2>{{ $title }}</h2><span class="muted mono">{{ $rows->count() }}</span></div>
<ul class="rows">@foreach($rows as $row)<li><a class="row" href="{{ $row['href'] }}"><span><strong>{{ $row['title'] }}</strong><span class="muted">{{ $row['sub'] }}</span></span>@if(!empty($row['status']))@include('staff.status',['status'=>$row['status']])@endif</a></li>@endforeach</ul>
</section>
@endif
@endforeach
@if($empty)<div class="panel empty">Aucun résultat pour « {{ $q }} ». Vérifiez l’orthographe ou cherchez par numéro.</div>@endif
@endif
@endsection

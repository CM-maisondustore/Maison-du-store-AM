@extends('staff.layout')
@section('title','Corbeille')
@section('subtitle','Éléments supprimés depuis l’espace pro. Restaurables pendant '.\App\Services\Trash::DAYS.' jours, puis supprimés définitivement.')
@section('content')
@error('trash')<p class="notice" role="alert">{{ $message }}</p>@enderror
@php($total=collect($groups)->sum(fn($g)=>$g['items']->count()))
@if(!$total)<section class="panel"><p class="empty">La corbeille est vide.</p></section>@endif
@foreach($groups as $type=>$group)
@if($group['items']->isNotEmpty())
<h2 class="section-title">{{ $group['label'] }} <b class="mono">{{ $group['items']->count() }}</b></h2>
@include('staff.partials.trash-table',['type'=>$type,'items'=>$group['items'],'compact'=>true])
@endif
@endforeach
@endsection

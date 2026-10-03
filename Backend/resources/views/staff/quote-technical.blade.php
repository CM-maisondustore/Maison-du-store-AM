@extends('staff.layout')
@section('title','Dossier de devis '.substr($quote->reference,0,8))
@section('content')
@include('staff.status',['status'=>$quote->status])
@if($quote->created_by===auth()->id() && in_array($quote->status,['pending','draft']))<p><a class="button" href="{{ route('staff.quotes.edit',$quote) }}">Modifier les choix et mesures</a></p>@endif
<section class="panel"><h2>Client</h2><p>{{ $quote->contact['firstName'] ?? '' }} {{ $quote->contact['lastName'] ?? '' }}<br>{{ $quote->contact['phone'] ?? '' }}<br>{{ $quote->contact['email'] ?? '' }}<br>{{ $quote->contact['address'] ?? '' }} {{ $quote->contact['city'] ?? '' }}</p></section>
<section class="panel">@include('staff.quote-windows-summary')</section>
<section class="panel"><h2>Notes de terrain</h2><p class="prose">{{ $quote->configuration['field_notes'] ?? '' }}</p></section>
<p>Le chiffrage et la validation sont effectués par Admin-CM.</p>
@endsection

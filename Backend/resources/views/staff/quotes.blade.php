@extends('staff.layout')
@section('title', auth()->user()->isAdmin() ? 'Devis' : 'Mes devis')
@section('subtitle', $quotes->total().' devis')
@section('page-actions')
<a class="button desktop-only" href="{{ route('staff.quotes.create') }}">@include('staff.partials.icon',['n'=>'plus','s'=>18])Nouveau devis</a>
@if(auth()->user()->isAdmin())<a class="button quiet desktop-only" href="{{ route('staff.quote-options.index') }}">Matériaux &amp; finitions</a>@endif
@endsection
@section('fab')<a class="fab fab-round" href="{{ route('staff.quotes.create') }}" aria-label="Nouveau devis" title="Nouveau devis">@include('staff.partials.icon',['n'=>'plus','s'=>26])</a>@endsection
@section('content')
@include('staff.partials.list-states',['type'=>'devis','state'=>$state])
@if($state==='corbeille')
@include('staff.partials.trash-table',['type'=>'devis','items'=>$quotes])
@else
@php($keep=request()->except(['status','page']))
<nav class="chips" aria-label="Filtrer par état">
<a class="chip" href="{{ route('staff.quotes.index',$keep) }}" @if(!request('status')) aria-current="true" @endif>Tous<b>{{ $counts->sum() }}</b></a>
@foreach(['pending'=>'À traiter','draft'=>'Brouillon','approved'=>'Validé','accepted'=>'Accepté','rejected'=>'Refusé','cancelled'=>'Annulé'] as $value=>$label)
<a class="chip" href="{{ route('staff.quotes.index',[...$keep,'status'=>$value]) }}" @if(request('status')===$value) aria-current="true" @endif>{{ $label }}<b>{{ $counts[$value] ?? 0 }}</b></a>
@endforeach
</nav>
<section class="panel flush" aria-label="Liste des devis">
<form class="filters" method="get" action="{{ route('staff.quotes.index') }}">
@if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
@if($state)<input type="hidden" name="etat" value="{{ $state }}">@endif
<label class="grow">Rechercher dans les devis<input type="search" name="q" maxlength="150" value="{{ request('q') }}" placeholder="Client ou référence"></label>
<details class="more-filters" @if(request()->hasAny(['type','source','agent'])) data-active @endif><summary>Autres filtres</summary><div>
<label>Type<select name="type"><option value="">Tous</option>@foreach(\App\Services\StaffInterface::QUOTE_TYPES as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Source<select name="source"><option value="">Toutes</option>@foreach(\App\Services\StaffInterface::SOURCES as $value=>$label)<option value="{{ $value }}" @selected(request('source')===$value)>{{ $label }}</option>@endforeach</select></label>
@if(auth()->user()->isAdmin())<label>Agent<select name="agent"><option value="">Tous</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(request('agent')==$agent->id)>{{ $agent->name }}</option>@endforeach<option value="none" @selected(request('agent')==='none')>Non affecté</option></select></label>@endif
</div></details>
<button class="secondary">Filtrer</button>
</form>
<div class="table-wrap">
<table class="cards">
<thead>
<tr>
<th scope="col">Client</th>
<th scope="col">Référence</th>
<th scope="col">Type</th>
<th scope="col">Source</th>
<th scope="col" aria-sort="{{ request('sort')==='asc' ? 'ascending' : 'descending' }}"><a class="row-link" href="{{ route('staff.quotes.index',[...request()->except('page'),'sort'=>request('sort')==='asc' ? 'desc' : 'asc']) }}">Date</a></th>
<th scope="col">Agent</th>
<th scope="col">État</th>
</tr>
</thead>
<tbody>
@forelse($quotes as $quote)<tr>
<td class="primary"><a class="row-link" href="{{ route('staff.quotes.show',$quote) }}">{{ \App\Services\StaffInterface::quoteClient($quote) }}</a><small>{{ $quote->contact['email'] ?? '' }}</small></td>
<td class="ref" data-label="Référence">{{ \App\Services\StaffInterface::ref($quote->reference) }}</td>
<td data-label="Type">{{ \App\Services\StaffInterface::QUOTE_TYPES[$quote->type] ?? ucfirst($quote->type) }}</td>
<td data-label="Source">{{ \App\Services\StaffInterface::SOURCES[$quote->source] ?? $quote->source }}</td>
<td class="mono" data-label="Date">{{ $quote->created_at->format('d/m/Y') }}</td>
<td data-label="Agent">{{ $quote->agent?->name ?? 'Non affecté' }}</td>
<td class="state">@include('staff.status',['status'=>$quote->status])</td>
</tr>
@empty<tr>
<td colspan="7" class="empty">Aucun devis à afficher. Choisissez un autre filtre ou créez un devis.</td>
</tr>
@endforelse</tbody>
</table>
</div>
@include('staff.pagination',['paginator'=>$quotes])
</section>
@endif
@endsection

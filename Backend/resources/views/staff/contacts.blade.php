@extends('staff.layout')
@section('title', 'Clients & contacts')
@section('subtitle', ($stats['client'] ?? 0).' client(s), '.($stats['fournisseur'] ?? 0).' fournisseur(s), '.($stats['partenaire'] ?? 0).' partenaire(s).')
@section('page-actions')<a class="button" href="#nouveau-contact">@include('staff.partials.icon',['n'=>'plus','s'=>18])Créer un client</a>@endsection
@section('content')
@include('staff.partials.list-states',['type'=>'clients','state'=>$state])
@if($state==='corbeille')
@include('staff.partials.trash-table',['type'=>'clients','items'=>$contacts])
@else
@php($keep=request()->except(['type','page']))
<nav class="chips" aria-label="Type de contact">
<a class="chip" href="{{ route('staff.contacts.index',$keep) }}" @if(!request('type')) aria-current="true" @endif>Tous<b>{{ $total }}</b></a>
@foreach(['client'=>'Clients','fournisseur'=>'Fournisseurs','partenaire'=>'Partenaires'] as $value=>$label)
<a class="chip" href="{{ route('staff.contacts.index',[...$keep,'type'=>$value]) }}" @if(request('type')===$value) aria-current="true" @endif>{{ $label }}<b>{{ $stats[$value] ?? 0 }}</b></a>
@endforeach
</nav>
<section class="panel flush" aria-label="Contacts">
<form method="get" class="filters">
@if(request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
@if($state)<input type="hidden" name="etat" value="{{ $state }}">@endif
<label class="grow">Rechercher<input type="search" name="q" placeholder="Nom, e-mail ou téléphone" value="{{ request('q') }}"></label>
<button class="secondary">Rechercher</button>
</form>
<div class="table-wrap">
<table class="cards">
<thead><tr><th scope="col">Contact</th><th scope="col">Coordonnées</th><th scope="col">Catégorie</th><th scope="col">État</th></tr></thead>
<tbody>
@forelse($contacts as $contact)<tr>
<td class="primary"><span class="title-row nowrap"><span class="avatar" aria-hidden="true">{{ \App\Services\StaffInterface::initials($contact->name) }}</span><a class="row-link" href="{{ route('staff.contacts.show',$contact) }}">{{ $contact->name }}</a></span></td>
<td data-label="Coordonnées">{{ $contact->email }}<small class="mono">{{ $contact->phone }}</small></td>
<td data-label="Catégorie">{{ ucfirst($contact->kind) }}</td>
<td data-label="État">{{ $contact->archived_at ? 'Archivé' : 'Actif' }}</td>
</tr>
@empty<tr><td colspan="4" class="empty">Aucun contact.</td></tr>
@endforelse</tbody>
</table>
</div>
@include('staff.pagination',['paginator'=>$contacts])
</section>
<section class="float-panel" id="nouveau-contact" @if($errors->any()) data-open @endif>
<form class="float-form" method="post" action="{{ route('staff.contacts.store') }}" role="dialog" aria-modal="true" aria-labelledby="h-nouveau-contact">
@csrf
<div class="float-head"><div><h2 id="h-nouveau-contact">Créer un client</h2><p class="muted">Client, fournisseur ou partenaire. Aucun message n’est envoyé.</p></div><a class="button quiet icon-btn" href="{{ route('staff.contacts.index') }}" data-close-panel aria-label="Fermer sans créer">@include('staff.partials.icon',['n'=>'close','s'=>18])</a></div>
<div class="float-body"><div class="float-cols">@include('staff.contact-fields',['contact'=>new \App\Models\Contact()])</div></div>
<div class="float-foot"><span class="status">Le dossier s’ouvre après la création.</span><a class="button quiet" href="{{ route('staff.contacts.index') }}" data-close-panel>Annuler</a><button>Créer un client</button></div>
</form>
</section>
@endif
@endsection

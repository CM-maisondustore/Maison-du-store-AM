@extends('staff.layout')
@section('title','Catalogue : tarifs, matériaux & finitions')
@section('crumbs')<a href="{{ route('staff.products.index') }}">Catalogue</a>@endsection
@section('subtitle','Tarifs utilisés par « Calculer depuis le catalogue » dans le chiffrage des devis.')
@section('page-actions')<a class="button" href="#ajouter-option">@include('staff.partials.icon',['n'=>'plus','s'=>18])Ajouter un matériau</a>@endsection
@section('content')
@php
 $missing=$options->where('active',true)->whereNull('unit_price')->count();
 $usedKinds=$options->pluck('kind')->unique();
 $failedForm=$errors->any() ? (string)old('open_form','new') : null;
 $blank=new \App\Models\QuoteOption(['active'=>true,'category'=>'rideau','kind'=>'material','motorization'=>'any']);
@endphp
@if($missing)<div class="notice" role="status"><strong>{{ $missing }} option(s) active(s) sans prix.</strong> Les lignes de devis qui les utilisent restent « À définir » jusqu’à ce qu’un prix soit saisi.</div>@endif
<div class="create-layout always">
<div class="create-list">
<p class="muted mt-0">Tarifs en FCFA, utilisés pour préparer les devis internes. Un prix vide signifie « à définir », jamais gratuit. Les métrages des rideaux sont saisis manuellement. Les accroches sont facturées par lots de 100. Les devis déjà enregistrés conservent leur chiffrage.</p>
<div class="chips" data-chip-filter="option-list">
<button type="button" class="chip" data-chip="all" aria-pressed="true">Tous<b>{{ $options->count() }}</b></button>
@foreach(\App\Models\QuoteOption::KINDS as $key=>$label)@if($usedKinds->contains($key))<button type="button" class="chip" data-chip="{{ $key }}" aria-pressed="false">{{ $label }}<b>{{ $options->where('kind',$key)->count() }}</b></button>@endif @endforeach
</div>
<div class="option-list" id="option-list">
@forelse($options as $option)
<details class="option {{ !$option->active ? 'is-off' : '' }}" data-chip-item="{{ $option->kind }}" @if($failedForm===(string)$option->id) open @endif><summary>
<span class="option-name"><strong>{{ $option->label }}</strong><small>{{ \App\Models\QuoteOption::KINDS[$option->kind] ?? $option->kind }}, {{ $option->category }}@php($calcBits=array_filter([\App\Models\QuoteOption::CALC_MODES[$option->kind][$option->calc_mode] ?? null,$option->calcCoefficient()!==null ? '× '.str_replace('.',',',$option->calcCoefficient()) : null,\App\Models\QuoteOption::CALC_BASES[$option->calc_basis] ?? null]))@if($calcBits) · Chiffrage : {{ implode(', ',$calcBits) }}@endif</small></span>
@if($option->unit_price===null)<span class="undefined option-price">À définir</span>@else<span class="amount option-price">{{ number_format($option->unit_price,0,',',' ') }} FCFA</span>@endif
<span class="badge {{ $option->active ? 'st-ok' : 'st-neutral' }}"><span aria-hidden="true">{{ $option->active ? '✓' : '○' }}</span>{{ $option->active ? 'Actif' : 'Inactif' }}</span>
</summary>
<div class="option-body">@include('staff.quote-option-form',['failed'=>$failedForm===(string)$option->id])</div>
</details>
@empty
<p class="empty">Aucun matériau pour le moment. Ajoutez le premier avec le panneau de création.</p>
@endforelse
</div>
</div>
<section class="panel create-panel" id="ajouter-option" aria-labelledby="h-ajouter-option" @if($failedForm==='new') data-open @endif>
<div class="create-head"><div><h2 id="h-ajouter-option">Ajouter un matériau</h2><p class="muted">La nature décide des champs utiles.</p></div><a class="button quiet icon-btn" href="{{ route('staff.quote-options.index') }}" data-close-panel aria-label="Fermer sans ajouter">@include('staff.partials.icon',['n'=>'close','s'=>18])</a></div>
@include('staff.quote-option-form',['option'=>$blank,'failed'=>$failedForm==='new'])
</section>
</div>
@endsection

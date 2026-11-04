@extends('staff.layout')
@php($isArticle=\App\Services\StaffInterface::isArticleQuote($quote))
@section('title','Dossier de devis '.\App\Services\StaffInterface::ref($quote->reference))
@section('back',route('staff.quotes.index'))
@section('crumbs')<a href="{{ route('staff.quotes.index') }}">Mes devis</a>@endsection
@section('page-actions')@include('staff.status',['status'=>$quote->status])@endsection
@if($quote->created_by===auth()->id() && in_array($quote->status,['pending','draft']))
@section('fab')<a class="button fab" href="{{ route('staff.quotes.edit',$quote) }}">Modifier les choix et mesures</a>@endsection
@endif
@section('content')
@if($quote->created_by===auth()->id() && in_array($quote->status,['pending','draft']))<p class="desktop-only"><a class="button" href="{{ route('staff.quotes.edit',$quote) }}">Modifier les choix et mesures</a></p>@endif
<div class="with-aside">
<div>
@if($isArticle)
<section class="panel"><h2>Articles</h2>
<ul class="rows">@foreach($quote->configuration['articles'] ?? [] as $article)<li class="row py-8"><span>{{ $article['name'] }}@if(!empty($article['variant']))<small class="muted"> · {{ $article['variant'] }}</small>@endif</span><span class="mono">× {{ $article['quantity'] }}</span></li>@endforeach</ul>
</section>
@else
<section class="panel">@include('staff.quote-windows-summary')</section>
@endif
<section class="panel"><h2>Notes de terrain</h2><p class="prose">{{ $quote->configuration['field_notes'] ?? '' }}</p></section>
<p class="muted">Le chiffrage et la validation sont effectués par Admin-CM.</p>
</div>
<aside>
<section class="panel"><h2>Client</h2>
<p><strong>{{ $quote->contact['firstName'] ?? '' }} {{ $quote->contact['lastName'] ?? '' }}</strong><br>
@if(!empty($quote->contact['phone']))<a href="tel:{{ preg_replace('/[^0-9+]/','',$quote->contact['phone']) }}">{{ $quote->contact['phone'] }}</a><br>@endif
{{ $quote->contact['email'] ?? '' }}<br>{{ $quote->contact['address'] ?? '' }} {{ $quote->contact['city'] ?? '' }}</p>
</section>
</aside>
</div>
@endsection

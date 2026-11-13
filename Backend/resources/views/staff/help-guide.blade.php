@extends('staff.layout')
@section('title',$guide['title'])
@section('back',route('staff.help.index'))
@section('crumbs')<a href="{{ route('staff.help.index') }}">Aide</a> / <span aria-current="page">{{ $guide['group'] }}</span>@endsection
@section('subtitle',$guide['summary'])
@section('page-actions')<button type="button" class="quiet" onclick="window.print()">Imprimer ce guide</button>@endsection
@section('content')
<div class="help-layout">
<article class="help-body">
{!! $html !!}
@if($next)
<a class="help-next" href="{{ route('staff.help.show',$next['slug']) }}"><span>Guide suivant</span><strong>{{ $next['title'] }}</strong></a>
@endif
</article>
@if($related)
<aside class="help-aside" aria-labelledby="help-related">
<h2 id="help-related">Dans « {{ $guide['group'] }} »</h2>
<ul>@foreach($related as $item)<li><a href="{{ route('staff.help.show',$item['slug']) }}">{{ $item['title'] }}</a></li>@endforeach</ul>
<p><a class="link" href="{{ route('staff.help.index') }}">Tous les guides</a></p>
</aside>
@endif
</div>
@endsection

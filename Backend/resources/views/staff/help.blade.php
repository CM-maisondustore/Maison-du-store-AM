@extends('staff.layout')
@section('title','Aide')
@section('subtitle','Guides pas à pas pour votre rôle : '.$role.'. Le bouton « ? » en haut de chaque page ouvre directement le guide de cette page.')
@section('content')
@php $first=collect($groups)->flatten(1)->first(); @endphp
<div class="help-index">
<form class="help-search" role="search" onsubmit="return false">
<label for="help-q">Rechercher dans les guides</label>
<input id="help-q" type="search" placeholder="{{ $role==='Agent' ? 'Par exemple : compte rendu, relevé, mot de passe' : 'Par exemple : acompte, WhatsApp, mot de passe' }}" autocomplete="off" data-help-search>
</form>
@if($first)
<a class="help-start" href="{{ route('staff.help.show',$first['slug']) }}" data-help-item data-search="{{ \App\Support\Help\HelpGuides::plain($first) }}">
<span class="help-start-k">Par où commencer</span>
<strong>{{ $first['title'] }}</strong>
<span>{{ $first['summary'] }}</span>
</a>
@endif
@foreach($groups as $group=>$guides)
<section class="help-group" aria-labelledby="hg-{{ $loop->index }}" data-help-group>
<h2 id="hg-{{ $loop->index }}">{{ $group }}</h2>
<ul class="help-list">
@foreach($guides as $guide)
<li data-help-item data-search="{{ \App\Support\Help\HelpGuides::plain($guide) }}"><a href="{{ route('staff.help.show',$guide['slug']) }}"><strong>{{ $guide['title'] }}</strong><span>{{ $guide['summary'] }}</span></a></li>
@endforeach
</ul>
</section>
@endforeach
<p class="help-empty muted" data-help-empty hidden>Aucun guide ne correspond à cette recherche. Essayez un autre mot, par exemple « devis » ou « facture ».</p>
</div>
<script>
(function(){
 var input=document.querySelector('[data-help-search]'); if(!input) return;
 var norm=function(s){return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');};
 input.addEventListener('input',function(){
  var words=norm(input.value).split(/\s+/).filter(Boolean), shown=0;
  document.querySelectorAll('[data-help-item]').forEach(function(item){
   var ok=words.every(function(w){return item.dataset.search.indexOf(w)!==-1;});
   item.hidden=!ok; if(ok && item.tagName==='LI') shown++;
  });
  document.querySelectorAll('[data-help-group]').forEach(function(g){ g.hidden=!g.querySelector('[data-help-item]:not([hidden])'); });
  document.querySelector('[data-help-empty]').hidden=shown>0;
 });
})();
</script>
@endsection

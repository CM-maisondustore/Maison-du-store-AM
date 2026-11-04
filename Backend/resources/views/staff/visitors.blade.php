@extends('staff.layout')
@section('title','Visiteurs')
@section('subtitle','Activité mesurée uniquement après consentement. Les adresses IP brutes ne sont pas enregistrées. Statistiques sur 30 jours.')
@php($originLabels=['google'=>'Google','facebook_instagram'=>'Facebook / Instagram','whatsapp'=>'WhatsApp','direct'=>'Lien direct','other_site'=>'Autre site'])
@section('content')
<nav class="queues" aria-label="Chiffres clés">
<a class="queue" href="#temps-reel"><span><strong id="live-visitors-count">{{ $live->count() }}</strong><b>en temps réel</b></span><small>Visiteurs des deux dernières minutes.</small></a>
<div class="queue"><span><strong>{{ $pageViews }}</strong><b>pages vues</b></span><small>Sur 30 jours.</small></div>
<div class="queue"><span><strong>{{ $events['form_submit'] ?? 0 }}</strong><b>formulaires reçus</b></span><small>{{ $abandoned }} commencés sans envoi (estimés).</small></div>
<div class="queue"><span><strong>{{ $returning }}</strong><b>visiteurs revenus</b></span><small>Sur 30 jours.</small></div>
</nav>
<div class="with-aside even">
<div>
<section class="panel flush" aria-labelledby="temps-reel"><div class="panel-head"><h2 id="temps-reel">Visiteurs en temps réel</h2></div>
<div class="panel-body" id="live-visitors-table">@include('staff.visitors-live')</div></section>
<div class="two-cols">
@foreach([['Origine des visiteurs',$origins->map(fn($row)=>[$originLabels[$row->origin] ?? $row->origin,$row->total])],['Pages les plus consultées',$pages->map(fn($row)=>[$row->path,$row->total])]] as [$title,$bars])
<section class="panel"><h2>{{ $title }}</h2>
@php($top=max(1,(int)$bars->max(1)))
@forelse($bars as [$label,$total])
<div class="meter" title="{{ $label }} : {{ $total }}"><span class="meter-l">{{ $label }}</span><span class="meter-v mono">{{ $total }}</span><span class="meter-t" aria-hidden="true"><i data-w="{{ round($total/$top*100) }}"></i></span></div>
@empty<p class="muted">Pas encore de données.</p>@endforelse
</section>
@endforeach
</div>
<section class="panel flush" aria-labelledby="visites-t"><div class="panel-head"><h2 id="visites-t">Visites par jour</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th class="num">Visites</th><th class="num">Visiteurs distincts</th></tr></thead><tbody>@forelse($daily as $day)<tr><td class="mono">{{ $day->day }}</td><td class="num mono">{{ $day->visits }}</td><td class="num mono">{{ $day->visitors }}</td></tr>@empty<tr><td colspan="3" class="empty">Pas encore de données consenties.</td></tr>@endforelse</tbody></table></div></section>
</div>
<aside>
<section class="panel"><h2>Alertes utiles</h2>
<ul class="check-list">
<li>{{ $todayEvents ? 'Nouveaux formulaires, devis ou rendez-vous reçus aujourd’hui : '.$todayEvents : 'Aucun nouveau formulaire reçu aujourd’hui.' }}</li>
<li>{{ $returning ? 'Des visiteurs reviennent régulièrement : '.$returning.' visiteurs sur 30 jours.' : 'Pas encore de retour régulier identifié.' }}</li>
<li>{{ $trafficSpike ? 'Trafic en forte hausse : '.$todayVisits.' visites aujourd’hui contre '.$yesterdayVisits.' hier.' : 'Aucune hausse de trafic inhabituelle détectée.' }}</li>
</ul></section>
<section class="panel flush" aria-labelledby="actions-t"><div class="panel-head"><h2 id="actions-t">Actions sur 30 jours</h2></div>
<dl class="kv">
<dt>Nouveaux devis demandés</dt><dd>{{ $events['quote_received'] ?? 0 }}</dd>
<dt>Nouveaux rendez-vous</dt><dd>{{ $events['appointment_received'] ?? 0 }}</dd>
<dt>Clics Demander un devis</dt><dd>{{ $events['quote_click'] ?? 0 }}</dd>
<dt>Intentions rendez-vous</dt><dd>{{ $events['appointment'] ?? 0 }}</dd>
<dt>Clics WhatsApp</dt><dd>{{ $events['whatsapp_click'] ?? 0 }}</dd>
<dt>Produits et services consultés</dt><dd>{{ $events['product_view'] ?? 0 }}</dd>
<dt>Formulaires commencés</dt><dd>{{ $events['form_start'] ?? 0 }}</dd>
</dl></section>
<section class="panel"><h2>Campagnes et pays</h2>
@forelse($campaigns as $campaign)<div class="meter-row"><span>{{ $campaign->campaign }}</span><strong class="mono">{{ $campaign->total }}</strong></div>@empty<p class="muted">Aucune campagne identifiée.</p>@endforelse
<p>Pays approximatifs : @forelse($countries as $country){{ $country->country_code }} ({{ $country->total }})@if(!$loop->last), @endif @empty Non fourni @endforelse</p>
<p class="muted">Le pays et la ville ne sont affichés que si l’hébergeur transmet une localisation approximative. L’adresse IP brute n’est pas conservée dans ce tableau de bord.</p></section>
</aside>
</div>
<script>
(() => { const count=document.getElementById('live-visitors-count'), table=document.getElementById('live-visitors-table');
  document.querySelectorAll('.meter-t i').forEach(bar=>{bar.style.width=bar.dataset.w+'%'});
  const refresh=()=>fetch(@json(route('staff.visitors.index',['live'=>1])),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(data=>{if(data){count.textContent=data.count;table.innerHTML=data.html;}}).catch(()=>{});
  window.setInterval(refresh,15000);
})();
</script>
@endsection

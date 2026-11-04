@extends('staff.layout')
@section('title','Visiteurs')
@section('content')
<p class="muted">Activité mesurée uniquement après consentement. Les adresses IP brutes ne sont pas enregistrées. Statistiques sur 30 jours.</p>
<section class="panel"><h2>Alertes utiles</h2><p>{{ $todayEvents ? 'Nouveaux formulaires, devis ou rendez-vous reçus aujourd’hui : '.$todayEvents : 'Aucun nouveau formulaire reçu aujourd’hui.' }}</p><p>{{ $returning ? 'Des visiteurs reviennent régulièrement : '.$returning.' visiteurs sur 30 jours.' : 'Pas encore de retour régulier identifié.' }}</p><p>{{ $trafficSpike ? 'Trafic en forte hausse : '.$todayVisits.' visites aujourd’hui contre '.$yesterdayVisits.' hier.' : 'Aucune hausse de trafic inhabituelle détectée.' }}</p></section>
<div class="stats">
<div class="stat"><strong id="live-visitors-count">{{ $live->count() }}</strong><span>Visiteurs en temps réel (2 min)</span></div>
<div class="stat"><strong>{{ $pageViews }}</strong><span>Pages vues · 30 jours</span></div>
<div class="stat"><strong>{{ $events['form_submit'] ?? 0 }}</strong><span>Formulaires reçus</span></div>
<div class="stat"><strong>{{ $events['quote_received'] ?? 0 }}</strong><span>Nouveaux devis demandés</span></div>
<div class="stat"><strong>{{ $events['appointment_received'] ?? 0 }}</strong><span>Nouveaux rendez-vous</span></div>
<div class="stat"><strong>{{ $events['quote_click'] ?? 0 }}</strong><span>Clics Demander un devis</span></div>
<div class="stat"><strong>{{ $events['appointment'] ?? 0 }}</strong><span>Intentions rendez-vous</span></div>
<div class="stat"><strong>{{ $events['whatsapp_click'] ?? 0 }}</strong><span>Clics WhatsApp</span></div>
<div class="stat"><strong>{{ $abandoned }}</strong><span>Formulaires commencés sans envoi (estimés)</span></div>
<div class="stat"><strong>{{ $returning }}</strong><span>Visiteurs revenus</span></div>
</div>
<section class="panel"><h2>Visiteurs en temps réel</h2>
<div id="live-visitors-table">@include('staff.visitors-live')</div></section>
<div class="grid">
<section class="panel"><h2>Origine des visiteurs · 30 jours</h2>@forelse($origins as $row)<p>{{ ['google'=>'Google','facebook_instagram'=>'Facebook / Instagram','whatsapp'=>'WhatsApp','direct'=>'Lien direct','other_site'=>'Autre site'][$row->origin] ?? $row->origin }} <strong>{{ $row->total }}</strong></p>@empty<p class="muted">Pas encore de données.</p>@endforelse</section>
<section class="panel"><h2>Pages les plus consultées · 30 jours</h2>@forelse($pages as $row)<p>{{ $row->path }} <strong>{{ $row->total }}</strong></p>@empty<p class="muted">Pas encore de données.</p>@endforelse</section>
<section class="panel"><h2>Actions et campagnes · 30 jours</h2><p>Produits/services consultés : {{ $events['product_view'] ?? 0 }}</p><p>Formulaires commencés : {{ $events['form_start'] ?? 0 }}</p><p>Pays approximatifs : @forelse($countries as $country){{ $country->country_code }} ({{ $country->total }})@if(!$loop->last), @endif @empty Non fourni @endforelse</p><h3>Campagnes</h3>@forelse($campaigns as $campaign)<p>{{ $campaign->campaign }} <strong>{{ $campaign->total }}</strong></p>@empty<p class="muted">Aucune campagne identifiée.</p>@endforelse<p class="muted">Le pays et la ville ne sont affichés que si l’hébergeur transmet une localisation approximative.</p></section>
</div>
<section class="panel"><h2>Visites par jour</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Visites</th><th>Visiteurs distincts</th></tr></thead><tbody>@forelse($daily as $day)<tr><td>{{ $day->day }}</td><td>{{ $day->visits }}</td><td>{{ $day->visitors }}</td></tr>@empty<tr><td colspan="3">Pas encore de données consenties.</td></tr>@endforelse</tbody></table></div></section>
<p class="muted">L’adresse IP brute n’est pas conservée dans ce tableau de bord.</p>
<script>
(() => { const count=document.getElementById('live-visitors-count'), table=document.getElementById('live-visitors-table');
  const refresh=()=>fetch(@json(route('staff.visitors.index',['live'=>1])),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(data=>{if(data){count.textContent=data.count;table.innerHTML=data.html;}}).catch(()=>{});
  window.setInterval(refresh,15000);
})();
</script>
@endsection

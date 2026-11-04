@extends('staff.layout')
@php
 $admin=auth()->user()->isAdmin();
 $kind=\App\Services\StaffInterface::JOB_KINDS[$job->kind] ?? $job->kind;
 $phone=preg_replace('/[^0-9+]/','',(string)$job->contact->phone);
 $closed=in_array($job->status,['completed','cancelled']);
 $order=['planned'=>0,'in_progress'=>1,'completed'=>2];
 $position=$order[$job->status] ?? -1;
 $linked=$job->quote_id && $job->quote?->canBeViewedBy(auth()->user()) ? $job->quote : null;
@endphp
@section('title',$kind.', '.$job->scheduled_at->format('H:i'))
@section('back',$admin ? route('staff.interventions.index') : route('staff.dashboard'))
@section('crumbs')<a href="{{ route('staff.interventions.index') }}">Prestations</a> / <span class="mono" aria-current="page">Prestation #{{ $job->id }}</span>@endsection
@section('page-actions')@include('staff.status',['status'=>$job->status])@endsection
@section('content')
@if($job->status!=='cancelled')
<section class="panel" aria-label="Avancement">
<ol class="rail">
@foreach(['Planifié','En cours','Terminé'] as $i=>$label)<li class="{{ $i<=$position ? 'done' : '' }} {{ $i===$position ? 'current' : '' }}" @if($i===$position) aria-current="step" @endif>{{ $label }}</li>@endforeach
</ol>
</section>
@endif
<div class="with-aside">
<div>
<section class="panel" aria-labelledby="cli">
<h2 id="cli">{{ $job->contact->name }}</h2>
<p class="prose mt-8 m-x0">{{ $job->address }}</p>
<p class="muted mono m-0">{{ $job->scheduled_at->format('d/m/Y à H:i') }} · {{ $job->duration_minutes }} minutes · {{ $job->agent->name }}</p>
<div class="contact-actions">
@if($phone)<a href="tel:{{ $phone }}">@include('staff.partials.icon',['n'=>'phone'])Appeler</a>@endif
<a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($job->address) }}" target="_blank" rel="noopener">@include('staff.partials.icon',['n'=>'map'])Itinéraire</a>
@if($phone)<a href="https://wa.me/{{ ltrim($phone,'+') }}" target="_blank" rel="noopener">@include('staff.partials.icon',['n'=>'whatsapp'])WhatsApp</a>@endif
</div>
@if($job->contact->email)<p class="muted mb-0">{{ $job->contact->email }}</p>@endif
</section>
@if($job->instructions)
<section class="panel consignes-card" aria-labelledby="cons"><h2 id="cons">Consignes</h2><p class="prose">{{ $job->instructions }}</p></section>
@endif
@if($linked && !empty($linked->dimensions))
<section class="panel" aria-labelledby="dv"><h2 id="dv">À poser</h2>
<ul class="rows">@foreach($linked->dimensions as $window)<li class="row py-8"><span>{{ $window['label'] ?? 'Ouverture' }}@if(!empty($window['product_name']))<small class="muted"> · {{ $window['product_name'] }}</small>@endif</span><span class="mono">{{ $window['width'] ?? '' }} × {{ $window['height'] ?? '' }}</span></li>@endforeach</ul>
<p class="muted">Mesures en cm.</p>
</section>
@endif
<form method="post" action="{{ route('staff.interventions.update',$job) }}" id="job-form" @unless($admin) data-local-draft="report-{{ $job->id }}" @endunless>
@csrf
@method('PUT')
@if($admin)
<section class="panel"><h2>Planification</h2>
@include('staff.planning-fields')
<label>Avancement<select name="status">
@foreach(['planned'=>'Planifié','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$job->status)===$value)>{{ $label }}</option>
@endforeach</select>
</label>
</section>
@endif
<section class="panel" aria-labelledby="cr"><h2 id="cr">Compte rendu de l’intervention</h2>
@if(!$admin && $closed)
<p class="prose">{{ $job->report ?: 'Aucun compte rendu.' }}</p>
@if($job->completed_at)<p class="muted">Intervention terminée le {{ $job->completed_at->format('d/m/Y à H:i') }}.</p>@endif
@else
<label for="report-text" class="mt-0">Ce qui a été fait, problèmes rencontrés, suite à donner (obligatoire pour terminer)</label>
<textarea id="report-text" name="report" rows="7" placeholder="Ex. : stores posés et réglés. Client satisfait, aucune réserve.">{{ old('report',$job->report) }}</textarea>
<p class="muted" data-report-help aria-live="polite" data-draft-status="report-{{ $job->id }}"></p>
@endif
</section>
@if($admin)
<div class="action-bar"><span class="status">L’agent est prévenu de la mise à jour.</span><button>Enregistrer le suivi</button></div>
@elseif(!$closed)
<div class="action-bar">
@if($job->status==='planned')<button class="block" name="status" value="in_progress">Démarrer l’intervention</button>
@else<button class="quiet" name="status" value="in_progress">Enregistrer le compte rendu</button><button name="status" value="completed" data-report-required="report-text">Terminer l’intervention</button>@endif
</div>
@endif
</form>
@if(!$admin && !$closed && $job->status==='in_progress')
<details class="panel"><summary>Revenir à l’état « Planifié »</summary>
<form method="post" action="{{ route('staff.interventions.update',$job) }}">@csrf @method('PUT')<input type="hidden" name="report" value="{{ $job->report }}"><button class="secondary mt-8" name="status" value="planned">Enregistrer le suivi</button></form>
</details>
@endif
</div>
<aside>
<section class="panel" aria-labelledby="devis-place"><h2 id="devis-place">Devis sur place</h2>
@if($job->quote_id)@if($job->quote?->canBeViewedBy(auth()->user()))<a class="link" href="{{ route('staff.quotes.show',$job->quote_id) }}">Ouvrir le devis lié</a>@else<p>Le devis lié est géré par son auteur et Admin-CM.</p>@endif
@else<form method="post" action="{{ route('staff.interventions.quote',$job) }}">
@csrf<label>Type de projet<select name="type">
<option value="store">Store</option>
<option value="rideau">Rideau</option>
<option value="electromenager">Électroménager</option>
<option value="meubles">Meubles</option>
</select>
</label>
<div class="actions">
<button class="secondary block">Créer un devis pour ce client</button>
</div>
</form>
@endif</section>
@if($admin)
<section class="panel"><h2>Dossier</h2><p><a class="link" href="{{ route('staff.contacts.show',$job->contact_id) }}">Voir la fiche client</a></p>
<p><a class="link" href="{{ route('staff.messages.create',['contact_id'=>$job->contact_id]) }}">Prévenir le client</a></p></section>
@endif
</aside>
</div>
@endsection

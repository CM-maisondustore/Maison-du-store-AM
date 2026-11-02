@extends('staff.layout')
@section('title','Documents & automatisations')
@section('subtitle','Personnalisez vos documents et messages. Les modèles sont enregistrés en base ; les fichiers déjà envoyés restent archivés dans leur version d’origine.')
@section('page-actions')
<a class="button quiet" href="{{ route('staff.messages.index',['direction'=>'outgoing']) }}">Brouillons et historique des envois</a>
<a class="button quiet" href="{{ route('staff.layouts.edit') }}">Mise en page des PDF</a>
<a class="button secondary" href="{{ route('staff.messages.create',['channel'=>'whatsapp']) }}">Préparer un WhatsApp</a>
@endsection
@section('content')
<div class="two-cols" aria-label="État des envois">
<section class="panel">
<div class="title-row between"><h2>Connexion SMTP</h2>@if($verified)<span class="badge st-ok"><span aria-hidden="true">✓</span>Vérifiée</span>@else<span class="badge st-warn"><span aria-hidden="true">!</span>À vérifier</span>@endif</div>
<p>Hostinger Email : <strong>{{ $verified ? 'connexion SMTP vérifiée' : 'connexion SMTP à vérifier' }}</strong>.</p>
@if($checks->get('smtp')?->checked_at)<p class="muted">Dernier contrôle : <span class="mono">{{ $checks['smtp']->checked_at->format('d/m/Y H:i') }}</span> (Abidjan).</p>@endif
<form method="post" action="{{ route('staff.communications.connection') }}">@csrf<button class="secondary">Vérifier la connexion sans envoyer d’e-mail</button></form>
</section>
<section class="panel">
<h2>Traitement automatique</h2>
<p>Dernier passage des règles : <span class="mono">{{ $checks->get('scheduler')?->checked_at?->format('d/m/Y H:i') ?? 'En attente du premier passage' }}</span> (Abidjan).</p>
@if($checks->get('scheduler') && !$checks['scheduler']->successful)<p class="notice">Le dernier traitement a échoué. Vérifiez la planification.</p>@endif
<form method="post" action="{{ route('staff.communications.process') }}" data-confirm="Exécuter les règles actives ? Les règles en mode automatique peuvent envoyer des e-mails aux clients.">@csrf<button>Exécuter les règles actives maintenant</button></form>
</section>
</div>
<p class="notice info">WhatsApp professionnel : {{ config('workspace.whatsapp_number') }}. Les messages sont préparés ici puis envoyés manuellement dans WhatsApp ; la connexion API n’est pas activée.</p>
<section class="panel flush" aria-labelledby="h-rules">
<div class="panel-head stacked"><h2 id="h-rules">Règles d’automatisation par e-mail</h2>
<p class="muted m-0">« Brouillon à valider » prépare le message sans l’envoyer. « Envoi automatique » autorise son envoi sans nouvelle validation. Les demandes et validations antérieures à l’activation ne sont pas reprises ; les rappels concernent les rendez-vous à venir. Une règle s’exécute au plus une fois par document ou créneau.</p></div>
<ul class="rows">
@foreach($rules as $rule)
<li><form class="rule-row" method="post" action="{{ route('staff.communications.rule',$rule) }}" data-confirm="Enregistrer ce mode ? Le mode automatique autorise les envois aux clients pour cette règle.">
@csrf @method('PUT')
<div class="rule-name"><h3>{{ \App\Services\CommunicationTemplates::LABELS[$rule->key] }}</h3>
<a class="link" href="{{ route('staff.communications.edit',$templates->where('channel','email')->firstWhere('key',$rule->key)) }}">Modifier le message</a></div>
<div class="rule-controls">
@if($rule->key==='reminder')<label class="inline-field">Heures avant le rendez-vous<input type="number" name="delay_hours" min="1" max="168" value="{{ $rule->delay_hours }}" required></label>@else<input type="hidden" name="delay_hours" value="{{ $rule->delay_hours }}">@endif
<fieldset class="segmented" aria-label="Mode"><legend class="sr-only">Mode de la règle</legend>
@foreach(['off'=>'Désactivée','draft'=>'Brouillon à valider','automatic'=>'Envoi automatique'] as $value=>$label)
<label><input type="radio" name="mode" value="{{ $value }}" @checked($rule->mode===$value) @disabled($value==='automatic' && !$verified)><span>{{ $label }}</span></label>
@endforeach
</fieldset>
<button class="quiet">Enregistrer la règle</button>
</div>
</form></li>
@endforeach
</ul>
</section>
<section class="panel flush" aria-labelledby="h-tpl">
<div class="panel-head" data-tabs><h2 id="h-tpl">Modèles</h2>
<div class="tabs" role="tablist" aria-labelledby="h-tpl">
@foreach($templates->groupBy('channel') as $channel=>$group)<a class="tab" role="tab" href="#tpl-{{ $channel }}" id="tab-{{ $channel }}" aria-controls="tpl-{{ $channel }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ ['pdf'=>'Devis, factures et reçus PDF','email'=>'E-mails','whatsapp'=>'WhatsApp'][$channel] }}<b>{{ $group->count() }}</b></a>@endforeach
</div></div>
@foreach($templates->groupBy('channel') as $channel=>$group)
<div class="panel-body" id="tpl-{{ $channel }}" role="tabpanel" aria-labelledby="tab-{{ $channel }}">
<div class="tile-group">
@foreach($group as $template)
<a class="tile plain" href="{{ route('staff.communications.edit',$template) }}"><span><strong>{{ $template->name }}</strong>
<small>Version {{ $template->channel==='pdf'?($template->published_version ?? $template->version):$template->version }}@if($template->layout_draft), brouillon en cours @endif</small></span></a>
@endforeach
</div>
</div>
@endforeach
</section>
@endsection

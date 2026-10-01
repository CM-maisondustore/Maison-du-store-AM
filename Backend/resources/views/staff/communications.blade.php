@extends('staff.layout')
@section('title','Documents & automatisations')
@section('content')
<p>Personnalisez vos documents et messages. Les modèles sont enregistrés en base ; les fichiers déjà envoyés restent archivés dans leur version d’origine.</p>
<div class="actions"><a href="{{ route('staff.messages.index',['direction'=>'outgoing']) }}">Brouillons et historique des envois</a><a class="button" href="{{ route('staff.messages.create',['channel'=>'whatsapp']) }}">Préparer un WhatsApp</a></div>
<section class="panel"><h2>Connexion et exécution</h2>
<p>Hostinger Email : <strong>{{ $verified ? 'connexion SMTP vérifiée' : 'connexion SMTP à vérifier' }}</strong>.</p>
@if($checks->get('smtp')?->checked_at)<p>Dernier contrôle : {{ $checks['smtp']->checked_at->format('d/m/Y H:i') }} (Abidjan).</p>@endif
<form method="post" action="{{ route('staff.communications.connection') }}">@csrf<button class="secondary">Vérifier la connexion sans envoyer d’e-mail</button></form>
<p>Dernier passage des règles : {{ $checks->get('scheduler')?->checked_at?->format('d/m/Y H:i') ?? 'En attente du premier passage' }} (Abidjan).</p>
@if($checks->get('scheduler') && !$checks['scheduler']->successful)<p class="notice">Le dernier traitement a échoué. Vérifiez la planification.</p>@endif
<form method="post" action="{{ route('staff.communications.process') }}" data-confirm="Exécuter les règles actives ? Les règles en mode automatique peuvent envoyer des e-mails aux clients.">@csrf<button class="secondary">Exécuter les règles actives maintenant</button></form>
<p>WhatsApp professionnel : {{ config('workspace.whatsapp_number') }}. Les messages sont préparés ici puis envoyés manuellement dans WhatsApp ; la connexion API n’est pas activée.</p></section>
<h2>Modèles</h2>
@foreach($templates->groupBy('channel') as $channel=>$group)
<section class="panel"><h3>{{ ['pdf'=>'Devis, factures et reçus PDF','email'=>'E-mails','whatsapp'=>'WhatsApp'][$channel] }}</h3>
<div class="grid">@foreach($group as $template)<div><a href="{{ route('staff.communications.edit',$template) }}">{{ $template->name }}</a><p class="muted">Version {{ $template->channel==='pdf'?($template->published_version ?? $template->version):$template->version }}@if($template->layout_draft) · brouillon en cours@endif</p></div>@endforeach</div></section>
@endforeach
<h2>Règles d’automatisation — e-mail</h2>
<p>« Brouillon à valider » prépare le message sans l’envoyer. « Envoi automatique » autorise son envoi sans nouvelle validation. Les demandes et validations antérieures à l’activation ne sont pas reprises ; les rappels concernent les rendez-vous à venir. Une règle s’exécute au plus une fois par document ou créneau.</p>
@foreach($rules as $rule)
<form class="panel" method="post" action="{{ route('staff.communications.rule',$rule) }}" data-confirm="Enregistrer ce mode ? Le mode automatique autorise les envois aux clients pour cette règle.">
@csrf @method('PUT')<h3>{{ \App\Services\CommunicationTemplates::LABELS[$rule->key] }}</h3>
<label>Mode<select name="mode">@foreach(['off'=>'Désactivée','draft'=>'Brouillon à valider','automatic'=>'Envoi automatique'] as $value=>$label)<option value="{{ $value }}" @selected($rule->mode===$value) @disabled($value==='automatic' && !$verified)>{{ $label }}</option>@endforeach</select></label>
@if($rule->key==='reminder')<label>Heures avant le rendez-vous<input type="number" name="delay_hours" min="1" max="168" value="{{ $rule->delay_hours }}" required></label>@else<input type="hidden" name="delay_hours" value="{{ $rule->delay_hours }}">@endif
<div class="actions"><button>Enregistrer la règle</button><a href="{{ route('staff.communications.edit',$templates->where('channel','email')->firstWhere('key',$rule->key)) }}">Modifier le message</a></div>
</form>@endforeach
@endsection

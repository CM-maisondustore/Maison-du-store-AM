@extends('staff.layout')
@php($me=auth()->user())
@php($prefs=$me->preferences ?? [])
@section('title','Paramètres')
@section('crumbs')<a href="{{ route('staff.profile') }}">Mon profil</a>@endsection
@section('back',route('staff.profile'))
@section('content')
<div class="mw-820">
<section class="panel" id="compte" aria-labelledby="s-compte"><h2 id="s-compte">Compte et sécurité</h2>
<dl class="detail-list"><dt>Identifiant de connexion</dt><dd class="mono">{{ $me->username }}</dd><dt>Adresse e-mail</dt><dd>{{ $me->email }}</dd></dl>
<div class="actions"><a class="button quiet" href="{{ route('staff.password') }}">Changer le mot de passe</a><a class="button quiet" href="{{ route('staff.profile') }}">Modifier mon profil</a></div>
<details><summary class="link">Se déconnecter de tous les autres appareils</summary>
<form method="post" action="{{ route('staff.settings.logout-others') }}">@csrf<label>Mot de passe actuel<input name="password" type="password" required autocomplete="current-password"></label><button class="secondary mt-8">Déconnecter les autres appareils</button></form>
</details>
</section>
<section class="panel" id="notifications" aria-labelledby="s-notif"><h2 id="s-notif">Notifications</h2>
<p class="muted">Les alertes arrivent dans votre espace professionnel. Sur cet appareil, activez ou coupez les notifications du navigateur depuis le menu de votre compte.</p>
<a class="button quiet" href="{{ route('staff.notifications.index') }}">Voir mes notifications</a>
</section>
<form method="post" action="{{ route('staff.settings.update') }}">
@csrf @method('PATCH')
<section class="panel" id="affichage" aria-labelledby="s-aff"><h2 id="s-aff">Affichage et préférences</h2>
<fieldset style="border:0;padding:0;margin:0"><legend class="legend">Thème</legend>
<div class="tile-group">
@foreach(['light'=>['Clair','Le thème de référence'],'dark'=>['Sombre','Pour les faibles lumières'],'auto'=>['Automatique','Suit le réglage de l’appareil']] as $value=>$theme)
<label class="tile"><input type="radio" name="theme" value="{{ $value }}" @checked(($prefs['theme'] ?? 'light')===$value)><span><strong>{{ $theme[0] }}</strong><small>{{ $theme[1] }}</small></span></label>
@endforeach
</div></fieldset>
<p class="muted">Le thème suit votre compte, sur tous vos appareils. Les PDF et les e-mails restent toujours en clair.</p>
<input type="hidden" name="reduce_motion" value="0">
<label class="switch mt-12"><input class="switch" role="switch" type="checkbox" name="reduce_motion" value="1" @checked($prefs['reduce_motion'] ?? false)>Réduire les animations</label>
<div class="divider"></div>
<h3>Formats</h3><p class="muted">Fixés pour toute l’équipe : dates JJ/MM/AAAA, heures sur 24 h.</p><p class="mono">{{ now()->format('d/m/Y') }} · 16:30</p>
<div class="actions mb-0"><button>Enregistrer</button></div>
</section>
</form>
@if($me->isOwner())
<section class="panel" id="entreprise" aria-labelledby="s-ent"><h2 id="s-ent">Entreprise et documents</h2>
<p class="muted">Les coordonnées, mentions et conditions reprises sur les devis et factures se règlent dans la mise en page. Aucune valeur légale n’est inventée : une facture ne peut pas être émise tant que ces champs sont vides.</p>
<div class="actions mb-0"><a class="button quiet" href="{{ route('staff.layouts.edit') }}">Mise en page des documents</a><a class="button quiet" href="{{ route('staff.communications.index') }}">Modèles, e-mails et automatisations</a></div>
</section>
@endif
<section class="panel" id="session" aria-labelledby="s-ses"><h2 id="s-ses">Session</h2>
<form method="post" action="{{ route('staff.logout') }}">@csrf<button class="secondary">Se déconnecter</button></form>
</section>
</div>
@endsection

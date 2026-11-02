@extends('staff.layout')
@section('title','Équipe')
@section('subtitle','Comptes de l’Espace Pro et ce que chaque rôle peut voir.')
@section('content')
<div class="with-aside">
<section class="panel flush table-wrap" aria-label="Membres">
<table class="cards">
<thead><tr><th scope="col">Collaborateur</th><th scope="col">Rôle</th><th scope="col">Accès</th></tr></thead>
<tbody>
@foreach($users as $user)<tr>
<td class="primary"><span class="title-row nowrap"><span class="avatar" aria-hidden="true">{{ \App\Services\StaffInterface::initials($user->name) }}</span><span><strong>{{ $user->name }}</strong><small class="muted">{{ $user->username }} · {{ $user->email }}</small>
@if(!empty($user->profile['phone']))<small class="muted mono">{{ $user->profile['phone'] }}</small>@endif
@if(!$user->isAdmin() && !empty($user->profile['emergency_name']))<small class="muted">Urgence : {{ $user->profile['emergency_name'] }} {{ $user->profile['emergency_phone'] ?? '' }}</small>@endif</span></span></td>
<td data-label="Rôle">{{ $user->isAdmin() ? 'Admin-CM' : 'Agent' }}</td>
<td>
@if($user->id !== auth()->id())<form method="post" action="{{ route('staff.users.update',$user) }}">
@csrf
@method('PUT')<span class="badge {{ $user->active ? 'st-ok' : 'st-neutral' }}"><span aria-hidden="true">{{ $user->active ? '✓' : '–' }}</span>{{ $user->active ? 'Actif' : 'Désactivé' }}</span>
<label>État<select name="active">
<option value="1" @selected($user->active)>Actif</option>
<option value="0" @selected(!$user->active)>Désactivé</option>
</select>
</label>
<label>Réinitialisation du mot de passe (optionnelle)<input name="password" type="password" minlength="12" autocomplete="new-password">
</label>
<button class="quiet mt-8">Mettre à jour</button>
</form>
@else Votre compte
@endif</td>
</tr>
@endforeach</tbody>
</table>
</section>
<aside>
<section class="panel" aria-labelledby="inv-t">
<h2 id="inv-t">Créer un accès interne</h2>
<form method="post" action="{{ route('staff.users.store') }}">
@csrf
<label>Nom<input name="name" required value="{{ old('name') }}"></label>
<label>Identifiant<input name="username" required value="{{ old('username') }}" autocomplete="off"></label>
<label>E-mail professionnel<input name="email" type="email" required value="{{ old('email') }}"></label>
<label>Rôle<select name="role"><option value="agent">Agent</option><option value="admin">Admin-CM</option></select></label>
<label>Mot de passe temporaire<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
<p class="muted">12 caractères minimum, majuscules, minuscules et chiffres. Transmettez-le au collaborateur par un canal privé ; il devra le changer à sa première connexion.</p>
<div class="actions"><button>Créer le compte</button></div>
</form>
</section>
</aside>
</div>
<section class="panel flush" aria-labelledby="perm-t">
<div class="panel-head"><h2 id="perm-t">Ce que voit chaque rôle</h2></div>
<div class="table-wrap"><table>
<thead><tr><th scope="col">Accès</th><th scope="col">Admin-CM</th><th scope="col">Agent</th></tr></thead>
<tbody>
@foreach([['Devis','Tous','Ceux qu’il a créés, partie technique seulement'],['Chiffrage, validation, paiements, PDF chiffré','Oui','Jamais'],['Créer un devis sur place','Oui','Oui, sans prix'],['Rendez-vous et prestations','Tout, planification comprise','Affectés : démarrer, compte rendu, terminer'],['Clients, catalogue, factures, messagerie, documents, images, équipe','Oui','Non'],['Profil et paramètres personnels','Oui','Oui']] as $row)
<tr><th scope="row" class="quiet">{{ $row[0] }}</th><td>{{ $row[1] }}</td><td>{{ $row[2] }}</td></tr>
@endforeach
</tbody>
</table></div>
</section>
@endsection

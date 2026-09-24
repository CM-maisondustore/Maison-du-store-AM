
@extends('staff.layout')
@section('title','Équipe')
@section('content')<div class="panel table-wrap">
<table>
<thead>
<tr>
<th>Collaborateur</th>
<th>Rôle</th>
<th>Accès</th>
</tr>
</thead>
<tbody>
@foreach($users as $user)<tr>
<td>{{ $user->name }}<small>{{ $user->username }} · {{ $user->email }}</small>
</td>
<td>{{ $user->isAdmin() ? 'Admin-CM' : 'Agent' }}</td>
<td>
@if($user->id !== auth()->id())<form method="post" action="{{ route('staff.users.update',$user) }}">
@csrf
@method('PUT')<label>État<select name="active">
<option value="1" @selected($user->active)>Actif</option>
<option value="0" @selected(!$user->active)>Désactivé</option>
</select>
</label>
<label>Réinitialisation du mot de passe (optionnelle)<input name="password" type="password" minlength="12" autocomplete="new-password">
</label>
<button class="secondary">Mettre à jour</button>
</form>
@else Votre compte
@endif</td>
</tr>
@endforeach</tbody>
</table>
</div>
<section class="panel">
<h2>Créer un accès interne</h2>
<form method="post" action="{{ route('staff.users.store') }}">
@csrf<div class="grid">
<label>Nom<input name="name" required value="{{ old('name') }}">
</label>
<label>Identifiant<input name="username" required value="{{ old('username') }}" autocomplete="off">
</label>
<label>E-mail professionnel<input name="email" type="email" required value="{{ old('email') }}">
</label>
<label>Rôle<select name="role">
<option value="agent">Agent</option>
<option value="admin">Admin-CM</option>
</select>
</label>
<label>Mot de passe temporaire<input name="password" type="password" required minlength="12" autocomplete="new-password">
</label>
</div>
<p class="muted">12 caractères minimum, majuscules, minuscules et chiffres. Transmettez-le au collaborateur par un canal privé ; il devra le changer à sa première connexion.</p>
<div class="actions">
<button>Créer le compte</button>
</div>
</form>
</section>
@endsection

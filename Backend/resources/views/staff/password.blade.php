
@extends('staff.layout')
@section('title', 'Changer mon mot de passe')
@section('content')<div class="panel small-form">
@if(auth()->user()->must_change_password)<p class="notice">Choisissez votre mot de passe personnel avant de continuer.</p>
@endif<form method="post" action="{{ route('staff.password.update') }}">
@csrf<label>Mot de passe actuel<input name="current_password" type="password" required autocomplete="current-password">
</label>
<label>Nouveau mot de passe<input name="password" type="password" minlength="12" required autocomplete="new-password">
</label>
<p class="muted">12 caractères minimum, avec majuscules, minuscules et chiffres.</p>
<label>Confirmer le nouveau mot de passe<input name="password_confirmation" type="password" required autocomplete="new-password">
</label>
<div class="actions">
<button>Enregistrer</button>
</div>
</form>
</div>
@endsection

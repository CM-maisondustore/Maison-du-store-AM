<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Connexion — Maison du Store AM</title>
<link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body>
<main class="auth">
<p class="eyebrow">Maison du Store AM</p>
<h1>Votre espace de travail</h1>
<p class="muted">Accès réservé à Admin-CM et aux agents.</p>
<div class="panel">
@include('staff.feedback')<form method="post" action="{{ route('staff.login') }}">
@csrf<label for="username">Identifiant</label>
<input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
<label for="password">Mot de passe</label>
<input id="password" type="password" name="password" required autocomplete="current-password">
<div class="actions">
<button>Se connecter</button>
</div>
</form>
<p class="muted">Mot de passe oublié ? Contactez votre administrateur pour le réinitialiser.</p>
</div>
<a href="{{ route('home') }}">Retour au site</a>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Connexion — Maison du Store AM</title>
<link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
</head>
<body>
<div class="auth-page">
<aside class="auth-side">
<span class="brand brand-plain"><span class="brand-logo"><img src="{{ asset('images/logo-maison-du-store-am.jpg') }}" alt=""></span><span class="brand-text"><strong>Maison du Store AM</strong><span>Espace professionnel</span></span></span>
<h2>Les coulisses de chaque fenêtre bien habillée.</h2>
<p>Stores, rideaux, électroménager et meubles : devis, rendez-vous, prestations et facturation au même endroit.</p>
</aside>
<main class="auth-main" id="contenu">
<span class="brand-logo mobile-only mb-8"><img src="{{ asset('images/logo-maison-du-store-am.jpg') }}" alt=""></span>
<h1>Votre espace de travail</h1>
<p class="muted">Accès réservé à Admin-CM et aux agents.</p>
@include('staff.feedback')
<form method="post" action="{{ route('staff.login') }}">
@csrf
<label for="username">Identifiant</label>
<input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
<label for="password">Mot de passe</label>
<input id="password" type="password" name="password" required autocomplete="current-password">
<div class="actions"><button class="block">Se connecter</button></div>
</form>
<p class="muted">Mot de passe oublié ? Contactez votre administrateur pour le réinitialiser.</p>
<a href="{{ route('home') }}">Retour au site</a>
</main>
</div>
</body>
</html>

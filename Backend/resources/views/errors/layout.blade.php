<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>@yield('heading') — Maison du Store AM</title>
<link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body class="error-page">
<main id="contenu" class="error-card">
<span class="bubble-c tone-@yield('tone','gold')" aria-hidden="true"><img src="{{ asset('images/logo-maison-du-store-am.jpg') }}" alt=""></span>
<p class="mono muted">Erreur @yield('code')</p>
<h1>@yield('heading')</h1>
<p>@yield('message')</p>
<div class="actions">@if(request()->is('admin*'))<a class="button" href="{{ url('/admin') }}">Revenir à l’espace pro</a>@else<a class="button" href="{{ url('/') }}">Revenir au site</a>@endif</div>
</main>
</body>
</html>

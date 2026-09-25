<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Espace interne') — Maison du Store AM</title>
<link rel="stylesheet" href="{{ asset('admin.css') }}">
<script src="{{ asset('admin.js') }}" defer>
</script>
</head>
<body>
<div class="shell">
<aside class="sidebar">
<div class="brand">Maison du Store <span>AM</span>
<small>ESPACE PROFESSIONNEL</small>
</div>
<nav aria-label="Navigation interne">
@php($menu = ['dashboard' => 'Vue d’ensemble', 'quotes.index' => 'Devis', 'appointments.index' => 'Rendez-vous', 'interventions.index' => 'Prestations'])
@if(auth()->user()->isAdmin())
@php($menu += ['contacts.index' => 'Clients & contacts', 'products.index' => 'Catalogue', 'contents.index' => 'Images & portfolio', 'invoices.index' => 'Factures', 'messages.index' => 'Messagerie', 'users.index' => 'Équipe'])
@endif
@foreach($menu as $route => $label)<a class="{{ request()->routeIs('staff.'.explode('.', $route)[0].'*') ? 'current' : '' }}" href="{{ route('staff.'.$route) }}">{{ $label }}</a>
@endforeach
</nav>
</aside>
<main class="main">
<header class="topbar">
<span>{{ auth()->user()->name }} · {{ auth()->user()->isAdmin() ? 'Admin-CM' : 'Agent' }}</span>
<div class="actions" style="margin:0">
<a href="{{ route('staff.password') }}">Mot de passe</a>
<a href="{{ route('home') }}" target="_blank" rel="noopener">Voir le site</a>
<form method="post" action="{{ route('staff.logout') }}">
@csrf<button class="secondary">Déconnexion</button>
</form>
</div>
</header>
@include('staff.feedback')
<p class="eyebrow">Maison du Store AM · Gestion</p>
<h1>@yield('title', 'Espace interne')</h1>@yield('content')</main>
</div>
</body>
</html>

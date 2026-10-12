<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#163b35">
<title>@yield('title', 'Espace interne') — Maison du Store AM</title>
<link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
<script src="{{ asset('admin.js') }}?v={{ filemtime(public_path('admin.js')) }}" defer>
</script>
<script src="{{ asset('admin-push.js') }}?v={{ filemtime(public_path('admin-push.js')) }}" defer></script>
</head>
<body>
<div class="shell">
<aside class="sidebar">
<div class="brand">Maison du Store <span>AM</span>
<small>ESPACE PROFESSIONNEL</small>
</div>
<div class="sidebar-controls"><button type="button" class="secondary" data-nav-scroll="-1" aria-label="Faire défiler le menu vers le début" aria-controls="staff-navigation">←</button><button type="button" class="secondary" data-nav-scroll="1" aria-label="Faire défiler le menu vers la fin" aria-controls="staff-navigation">→</button></div>
<nav id="staff-navigation" tabindex="0" aria-label="Navigation interne">
@php($menu = ['dashboard' => 'Vue d’ensemble', 'notifications.index' => 'Notifications', 'quotes.index' => 'Devis', 'appointments.index' => 'Rendez-vous', 'interventions.index' => 'Prestations'])
@if(auth()->user()->isAdmin())
@php($menu += ['visitors.index'=>'Visiteurs'])
@php($menu += ['layouts.edit'=>'Mise en page','communications.index' => 'Documents & automatisations'])
@php($menu += ['contacts.index' => 'Clients & contacts', 'products.index' => 'Catalogue', 'contents.index' => 'Images & portfolio', 'invoices.index' => 'Factures', 'messages.index' => 'Messagerie', 'users.index' => 'Équipe'])
@endif
@foreach($menu as $route => $label)<a class="{{ request()->routeIs('staff.'.explode('.', $route)[0].'*') ? 'current' : '' }}" href="{{ route('staff.'.$route) }}">{{ $label }}@if($route === 'notifications.index') <span class="notification-count">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
@endforeach
</nav>
</aside>
<main class="main">
<header class="topbar">
<span>{{ auth()->user()->name }} · {{ auth()->user()->isAdmin() ? 'Admin-CM' : 'Agent' }}</span>
<div class="actions" style="margin:0">
<button type="button" class="secondary" id="push-toggle" data-configured="{{ filled(config('push.public_key')) ? '1' : '0' }}" data-public-key-url="{{ route('staff.push.public-key') }}" data-subscribe-url="{{ route('staff.push.subscribe') }}" data-unsubscribe-url="{{ route('staff.push.unsubscribe') }}">Activer les notifications</button>
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

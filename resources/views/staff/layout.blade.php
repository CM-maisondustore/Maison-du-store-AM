@php
 $me=auth()->user();
 $ui=\App\Services\StaffInterface::navigation($me);
 $prefs=$me->preferences ?? [];
 $initials=\App\Services\StaffInterface::initials($me->name);
 $pageTitle=html_entity_decode(trim($__env->yieldContent('title','Espace interne')),ENT_QUOTES|ENT_HTML5,'UTF-8');
@endphp
<!DOCTYPE html>
<html lang="fr" data-theme="{{ $prefs['theme'] ?? 'light' }}" @if(!empty($prefs['reduce_motion'])) data-motion="reduced" @endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#20332c">
<title>@yield('title', 'Espace interne') — Maison du Store AM</title>
<script>try{var s=localStorage.getItem('mds-sidebar');if(s==='collapsed'||(s===null&&innerWidth<1200))document.documentElement.dataset.sidebar='collapsed'}catch(e){}</script>
<link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
<script src="{{ asset('admin.js') }}?v={{ filemtime(public_path('admin.js')) }}" defer></script>
<script src="{{ asset('admin-push.js') }}?v={{ filemtime(public_path('admin-push.js')) }}" defer></script>
</head>
<body class="@hasSection('actionbar') has-action-bar @endif @yield('body-class')" @if(session('success')) data-flash="success" @endif data-notifications-url="{{ route('staff.notifications.recent') }}" data-notifications-page="{{ route('staff.notifications.index') }}" @if(request()->routeIs('staff.notifications.index')) data-notifications-here @endif>
<a class="skip" href="#contenu">Aller au contenu</a>
<div class="shell">
<nav class="sidebar" aria-label="Modules de l’Espace Pro">
<a class="brand" href="{{ route('staff.dashboard') }}">
<span class="brand-logo"><img src="{{ asset('images/logo-maison-du-store-am.jpg') }}" alt=""></span>
<span class="brand-text"><strong>Maison du Store AM</strong><span>Espace professionnel</span></span>
</a>
<div class="nav-scroll">
@foreach($ui['sections'] as $section)
<div class="nav-section">
@if($section['title'])<span class="nav-title">{{ $section['title'] }}</span>@endif
@foreach($section['items'] as $item)
<a class="nav-link" href="{{ $item['href'] }}" title="{{ $item['label'] }}" @if($item['current']) aria-current="page" @endif>
@include('staff.partials.icon',['n'=>$item['icon']])<span class="nav-label">{{ $item['label'] }}</span>
@if($item['count'])<span class="nav-count" aria-label="{{ $item['count'] }} {{ $item['countLabel'] }}">{{ $item['count'] }}</span>@endif
</a>
@endforeach
</div>
@endforeach
</div>
<div class="nav-foot">
<a class="nav-link" href="{{ route('home') }}" target="_blank" rel="noopener" title="Voir le site">@include('staff.partials.icon',['n'=>'external'])<span class="nav-label">Voir le site</span></a>
<button type="button" class="nav-link" data-collapse aria-expanded="true" aria-label="Réduire ou déplier le menu">@include('staff.partials.icon',['n'=>'chevron-left'])<span class="nav-label">Réduire le menu</span></button>
</div>
</nav>
<div class="workspace">
<header class="topbar">
<form class="search" role="search" method="get" action="{{ route('staff.search') }}">
<label class="sr-only" for="pro-search">Recherche globale</label>
@include('staff.partials.icon',['n'=>'search','s'=>18])
<input id="pro-search" type="search" name="q" value="{{ request()->routeIs('staff.search') ? request('q') : '' }}" placeholder="{{ $me->isAdmin() ? 'Client, téléphone, n° de devis ou de facture' : 'Client, n° de devis ou prestation' }}">
</form>
<div class="top-actions">
@if($me->isAdmin())
<details class="menu">
<summary class="button"><span aria-hidden="true">@include('staff.partials.icon',['n'=>'plus','s'=>18])</span>Nouveau</summary>
<div class="menu-list" role="menu">
<a role="menuitem" href="{{ route('staff.quotes.create') }}">Devis</a>
<a role="menuitem" href="{{ route('staff.interventions.index') }}#planifier">Prestation</a>
<a role="menuitem" href="{{ route('staff.contacts.index') }}#nouveau-contact">Client</a>
<a role="menuitem" href="{{ route('staff.messages.create') }}">Message</a>
</div>
</details>
@endif
<div class="bell-wrap"><a class="bell" href="{{ route('staff.notifications.index') }}" aria-label="Notifications : {{ $ui['notifications'] }} non lue(s)" data-bell>@include('staff.partials.icon',['n'=>'bell'])<b class="notification-count" @if(!$ui['notifications']) hidden @endif>{{ $ui['notifications'] }}</b></a>
<div class="bell-pop" data-bell-pop hidden><div class="bell-pop-head"><h2>Notifications</h2></div><ul class="bell-list" data-bell-list></ul><a class="bell-pop-foot" href="{{ route('staff.notifications.index') }}">Voir toutes les notifications</a></div></div>
<details class="menu">
<summary class="menu-trigger">
<span class="avatar" aria-hidden="true">{{ $initials }}</span>
<span class="user-who"><strong>{{ $me->name }}</strong><span>{{ $me->isAdmin() ? 'Admin-CM' : 'Agent' }}</span></span>
@include('staff.partials.icon',['n'=>'chevron-down','s'=>16])
</summary>
<div class="menu-list" role="menu">
<a role="menuitem" href="{{ route('staff.profile') }}">Mon profil</a>
<a role="menuitem" href="{{ route('staff.settings') }}">Paramètres</a>
<a role="menuitem" href="{{ route('staff.password') }}">Changer le mot de passe</a>
<button type="button" role="menuitem" id="push-toggle" data-configured="{{ filled(config('push.public_key')) ? '1' : '0' }}" data-public-key-url="{{ route('staff.push.public-key') }}" data-subscribe-url="{{ route('staff.push.subscribe') }}" data-unsubscribe-url="{{ route('staff.push.unsubscribe') }}">Activer les notifications</button>
<a role="menuitem" href="{{ route('home') }}" target="_blank" rel="noopener">Voir le site</a>
<hr>
<form method="post" action="{{ route('staff.logout') }}">@csrf<button role="menuitem">Se déconnecter</button></form>
</div>
</details>
</div>
</header>
<div class="mobile-head">
@hasSection('back')<a href="@yield('back')" aria-label="Retour">@include('staff.partials.icon',['n'=>'chevron-left','s'=>24])</a>@endif
<strong aria-hidden="true">{{ $pageTitle }}</strong>
<a href="{{ route('staff.search') }}" aria-label="Recherche globale">@include('staff.partials.icon',['n'=>'search','s'=>22])</a>
<a href="{{ route('staff.notifications.index') }}" class="bell" aria-label="Notifications : {{ $ui['notifications'] }} non lue(s)">@include('staff.partials.icon',['n'=>'bell','s'=>22])<b class="notification-count" @if(!$ui['notifications']) hidden @endif>{{ $ui['notifications'] }}</b></a>
</div>
<main class="main" id="contenu">
@include('staff.feedback')
@hasSection('head')
@yield('head')
@else
<div class="page-head">
<div>
@hasSection('crumbs')<nav class="crumbs" aria-label="Fil d’Ariane">@yield('crumbs')</nav>@endif
<h1>@yield('title', 'Espace interne')</h1>
@hasSection('subtitle')<p class="sub">@yield('subtitle')</p>@endif
</div>
@hasSection('page-actions')<div class="page-actions">@yield('page-actions')</div>@endif
</div>
@endif
@yield('content')
@yield('actionbar')
</main>
</div>
</div>
@yield('fab')
<nav class="tabbar" aria-label="Navigation principale">
@foreach($ui['tabs'] as $tab)
<a href="{{ $tab['href'] }}" @if($tab['current']) aria-current="page" @endif>@include('staff.partials.icon',['n'=>$tab['icon'],'s'=>24])@if($tab['count'])<b aria-label="{{ $tab['count'] }} {{ $tab['countLabel'] }}">{{ $tab['count'] }}</b>@endif<span>{{ $tab['label'] }}</span></a>
@endforeach
@if($me->isAdmin())<button type="button" data-open-dialog="more-sheet" aria-haspopup="dialog">@include('staff.partials.icon',['n'=>'more','s'=>24])<span>Plus</span></button>@endif
</nav>
@if($me->isAdmin())
<dialog class="sheet" id="more-sheet" aria-labelledby="more-title">
<div class="sheet-head"><h2 id="more-title">Autres modules</h2><button type="button" class="quiet icon-btn" data-close-dialog aria-label="Fermer">@include('staff.partials.icon',['n'=>'close'])</button></div>
@foreach($ui['more'] as $group)
<div class="sheet-group"><h3>{{ $group['title'] }}</h3>
@foreach($group['items'] as $item)<a class="sheet-link" href="{{ $item['href'] }}">@include('staff.partials.icon',['n'=>$item['icon']])<span>{{ $item['label'] }}</span>@if($item['count'])<span class="nav-count" aria-label="{{ $item['count'] }} {{ $item['countLabel'] }}">{{ $item['count'] }}</span>@endif</a>@endforeach
</div>
@endforeach
<div class="sheet-group"><h3>Mon compte</h3>
<a class="sheet-link" href="{{ route('staff.profile') }}">Modifier mon profil</a>
<a class="sheet-link" href="{{ route('staff.settings') }}">Paramètres</a>
<a class="sheet-link" href="{{ route('staff.password') }}">Changer le mot de passe</a>
<a class="sheet-link" href="{{ route('home') }}" target="_blank" rel="noopener">Voir le site</a>
</div>
</dialog>
@endif
<div class="bubble-tray" data-bubble-tray role="status" aria-live="polite" aria-label="Nouvelles notifications"></div>
<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-title">
<div class="modal-body"><h2 id="confirm-title">Confirmer</h2><p data-confirm-text></p>
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Annuler</button><button type="button" data-confirm-ok>Confirmer</button></div></div>
</dialog>
@if($errors->any())<script type="application/json" id="form-errors">@json($errors->toArray())</script>@endif
</body>
</html>

@php
    $active = $active ?? 'home';
    $shop = $shop ?? '';
    $pill = $pill ?? false;
    $catalogue = \App\Support\Site\Catalog::visible();
    $v = fn (string $f) => asset($f).'?v='.@filemtime(public_path($f));
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1c1c1c">
<title>@yield('title', 'Maison du Store AM — Stores et rideaux sur mesure à Abidjan')</title>
<meta name="description" content="@yield('description', 'Stores et rideaux sur mesure, confectionnés à Abidjan, livrés et posés par nos équipes. Maison du Store AM, showroom à Cocody.')">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="canonical" href="{{ url()->current() }}">
<link rel="icon" href="{{ asset('site/favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/inter-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $v('site/css/mds.css') }}">
@stack('head')
</head>
<body class="@yield('body_class')">
<a class="skip" href="#contenu">Aller au contenu principal</a>
@include('site.partials.header')
@yield('content')
@include('site.partials.footer')
<script src="{{ $v('site/js/mds.js') }}" defer></script>
@stack('scripts')
</body>
</html>

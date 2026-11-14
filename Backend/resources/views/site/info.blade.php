@extends('site.layout', ['active' => ''])
@section('title', $title.' — Maison du Store AM')
@section('description', $page === 'contact'
    ? 'Showroom de Cocody, téléphones, e-mail et WhatsApp de Maison du Store AM, stores et rideaux sur mesure à Abidjan.'
    : 'Maison du Store AM : stores, rideaux et voilages mesurés chez vous, confectionnés dans notre atelier d’Abidjan, livrés et posés par nos équipes.')
@section('body_class', 'bg-cream')
@section('content')
<main id="contenu" class="wrap page-pad legal">
    <nav aria-label="La maison" class="legal-nav">
        @foreach(\App\Http\Controllers\Site\SiteController::INFO as $k => $label)
            <a href="{{ route('site.info', $k) }}" @class(['on' => $k === $page]) @if($k === $page) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <h1 class="h1-52">{{ $title }}</h1>
    <div class="legal-body">
        @include('site.info.'.$page)
    </div>
</main>
@endsection

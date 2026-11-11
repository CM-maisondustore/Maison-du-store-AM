@extends('site.layout', ['active' => ''])
@section('title', $title.' — Maison du Store AM')
@section('body_class', 'bg-cream')
@section('content')
<main id="contenu" class="wrap page-pad legal">
    <nav aria-label="Pages légales" class="legal-nav">
        @foreach(\App\Http\Controllers\Site\SiteController::LEGAL as $k => $label)
            <a href="{{ route('site.legal', $k) }}" @class(['on' => $k === $page]) @if($k === $page) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <h1 class="h1-52">{{ $title }}</h1>
    <div class="legal-body">
        @include('site.legal.'.$page)
        <p class="legal-date">Version du {{ config('site.legal.updated') }}.</p>
    </div>
</main>
@endsection

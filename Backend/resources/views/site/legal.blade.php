@extends('site.layout', ['active' => ''])
@section('title', $title.' — Maison du Store AM')
@section('body_class', 'bg-cream')
@section('content')
<main id="contenu" class="wrap page-pad legal">
    <h1 class="h1-52">{{ $title }}</h1>
    @if($page === 'cookies')
        <p>Ce site n’utilise que les cookies nécessaires à son fonctionnement : votre session et la protection des formulaires. Aucun cookie publicitaire ni de mesure d’audience n’est déposé sur ces pages.</p>
        <p>Les polices de caractères sont servies par notre propre serveur : aucune donnée n’est transmise à un service tiers lors de votre visite.</p>
    @elseif($page === 'confidentialite')
        <p>Les informations saisies dans les formulaires de devis et de rendez-vous servent uniquement à établir votre devis, organiser la prise de mesures et vous recontacter. Elles sont conservées dans l’espace de gestion de Maison du Store AM et ne sont ni vendues ni cédées.</p>
        <p>Vous pouvez demander à consulter, corriger ou supprimer vos informations en nous écrivant.</p>
    @else
        <p class="note-gold">Texte en cours de rédaction.</p>
    @endif
    <p>Pour toute question : <a href="mailto:{{ config('site.contact.email') }}" class="alink">{{ config('site.contact.email') }}</a> ou {{ config('site.contact.phones')[0]['label'] }}.</p>
</main>
@endsection

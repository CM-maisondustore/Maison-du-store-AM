@extends('staff.layout')
@section('title', 'Paramètres')
@section('content')
<section class="panel settings-panel">
    <h2>Apparence</h2>
    <p class="muted">Ce choix s’applique à votre compte professionnel sur vos appareils.</p>
    <form method="post" action="{{ route('staff.settings.update') }}">
        @csrf
        @method('PATCH')
        <fieldset class="theme-options">
            <legend>Mode d’affichage</legend>
            <label class="theme-choice">
                <input type="radio" name="theme" value="light" @checked(old('theme', $user->theme ?? 'light') === 'light')>
                <span class="theme-preview theme-preview-light" aria-hidden="true"></span>
                <span><strong>Clair</strong><small>Fond blanc et texte sombre</small></span>
            </label>
            <label class="theme-choice">
                <input type="radio" name="theme" value="dark" @checked(old('theme', $user->theme ?? 'light') === 'dark')>
                <span class="theme-preview theme-preview-dark" aria-hidden="true"></span>
                <span><strong>Sombre</strong><small>Fond foncé et texte clair</small></span>
            </label>
        </fieldset>
        <div class="actions"><button type="submit">Enregistrer les paramètres</button></div>
    </form>
</section>
@endsection

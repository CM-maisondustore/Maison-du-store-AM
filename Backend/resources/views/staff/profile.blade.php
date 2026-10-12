@extends('staff.layout')
@section('title', 'Mon profil')
@section('content')
<section class="panel profile-panel">
    <p class="muted">Modifiez vos coordonnées professionnelles et votre photo. Le nom d’utilisateur et le mot de passe ne sont pas modifiés ici.</p>
    <form method="post" action="{{ route('staff.profile.update') }}" enctype="multipart/form-data" class="profile-form">
        @csrf
        @method('PATCH')
        <div class="profile-photo-field">
            @if($user->profile_photo && $user->profile_photo_mime)
                <img class="profile-avatar" src="data:{{ $user->profile_photo_mime }};base64,{{ $user->profile_photo }}" alt="Photo de {{ $user->name }}">
            @else
                <div class="profile-avatar profile-avatar-placeholder" aria-label="Aucune photo de profil">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            @endif
            <div>
                <label for="profile-photo">Photo de profil</label>
                <input id="profile-photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                <p class="muted">JPEG, PNG ou WebP, 2 Mo maximum. L’image est conservée dans la base et visible uniquement dans l’espace professionnel.</p>
                @if($user->profile_photo)
                    <label class="check"><input type="checkbox" name="remove_profile_photo" value="1">Retirer la photo actuelle</label>
                @endif
            </div>
        </div>
        <div class="profile-fields">
            <label for="profile-name">Nom affiché<input id="profile-name" name="name" value="{{ old('name', $user->name) }}" maxlength="150" required autocomplete="name"></label>
            <label for="profile-email">Adresse e-mail<input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="200" required autocomplete="email"></label>
            <label for="profile-phone">Téléphone professionnel<input id="profile-phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="32" autocomplete="tel" placeholder="+225 …"></label>
            <label>Nom d’utilisateur<input value="{{ $user->username }}" readonly aria-describedby="username-note"></label>
            <p class="muted" id="username-note">Le nom d’utilisateur reste inchangé pour protéger votre accès.</p>
        </div>
        <div class="actions"><button type="submit">Enregistrer le profil</button></div>
    </form>
</section>
@endsection

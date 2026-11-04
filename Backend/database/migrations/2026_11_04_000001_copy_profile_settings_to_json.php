<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

// L'Espace Pro v2 lit le téléphone dans users.profile et le thème dans users.preferences.
// Recopie des colonnes de 2026_10_12_000002 (déjà en production) ; elles sont conservées, rien n'est supprimé.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumns('users', ['phone', 'theme', 'profile', 'preferences'])) return;
        DB::table('users')->select(['id', 'phone', 'theme', 'profile', 'preferences'])->orderBy('id')->each(function ($user) {
            $profile = json_decode($user->profile ?? '', true) ?: [];
            $preferences = json_decode($user->preferences ?? '', true) ?: [];
            if (filled($user->phone) && empty($profile['phone'])) $profile['phone'] = $user->phone;
            if (in_array($user->theme, ['light', 'dark'], true) && empty($preferences['theme'])) $preferences['theme'] = $user->theme;
            DB::table('users')->where('id', $user->id)->update([
                'profile' => $profile ? json_encode($profile, JSON_UNESCAPED_UNICODE) : null,
                'preferences' => $preferences ? json_encode($preferences) : null,
            ]);
        });
    }

    // Les valeurs d'origine sont restées dans leurs colonnes : rien à défaire.
    public function down(): void {}
};

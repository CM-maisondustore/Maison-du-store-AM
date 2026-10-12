<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create([
            'username' => 'agent-'.Str::lower(Str::random(10)),
            'name' => 'Agent Test',
            'email' => Str::random(10).'@example.invalid',
            'password' => 'InitialPassword123!',
            'role' => 'agent',
            'must_change_password' => false,
        ]);
    }

    public function test_staff_can_edit_profile_photo_and_contact_details_without_changing_password(): void
    {
        $user = $this->staff();
        $originalPassword = $user->password;
        $this->actingAs($user);

        $this->get('/admin/profil')->assertOk()
            ->assertSee('Photo de profil')
            ->assertSee('name="profile_photo"', false)
            ->assertSee('id="pro-menu-toggle"', false)
            ->assertSee('id="pro-menu-panel"', false)
            ->assertSee('aria-label="Créer un nouveau devis"', false)
            ->assertDontSee('mobile-fab-label', false)
            ->assertSee('Paramètres')
            ->assertSee('Déconnexion')
            ->assertDontSee('name="password"', false);

        $this->patch('/admin/profil', [
            'name' => 'Agent Modifié',
            'email' => 'agent.modifie@example.invalid',
            'phone' => '+225 07 12 34 56 78',
            'profile_photo' => UploadedFile::fake()->image('profil.png', 96, 96),
            'password' => 'IgnoredNewPassword123!',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/profil');

        $user->refresh();
        $this->assertSame('Agent Modifié', $user->name);
        $this->assertSame('agent.modifie@example.invalid', $user->email);
        $this->assertSame('+225 07 12 34 56 78', $user->phone);
        $this->assertSame('image/png', $user->profile_photo_mime);
        $this->assertNotEmpty($user->profile_photo);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_staff_can_save_dark_theme_preference_and_it_is_rendered_on_next_page(): void
    {
        $user = $this->staff();
        $this->actingAs($user);

        $this->patch('/admin/parametres', ['theme' => 'dark'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/parametres');

        $this->assertSame('dark', $user->fresh()->theme);
        $this->get('/admin/parametres')->assertOk()->assertSee('data-theme="dark"', false);
    }
}

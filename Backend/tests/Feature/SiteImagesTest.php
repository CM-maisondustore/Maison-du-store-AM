<?php

namespace Tests\Feature;

use App\Models\{SiteContent, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Images et portfolio : chaque image fixe du site est administrable, avec retour à l'image d'origine. */
class SiteImagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => (string) Str::uuid(), 'name' => 'Admin', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => 'admin', 'must_change_password' => false]);
    }

    public function test_admin_lists_every_site_image_and_replaces_one(): void
    {
        $this->seed();
        $this->actingAs($this->admin());
        $this->get('/')->assertOk()->assertSee('site/images/soon/electro.jpg', false)->assertSee('site/images/realisations/wave-luxe.jpg', false);

        $this->get(route('staff.contents.index'))->assertOk()
            ->assertSee('Images du site')->assertSee('Carte Électroménager')->assertSee('Image du menu Rideaux &amp; voilages', false)
            ->assertSee('Photo du showroom')->assertSee('Image d’origine du site')->assertSee('Réalisations');
        $card = SiteContent::where('slot', 'boutique-electro')->firstOrFail();
        $this->get(route('staff.contents.edit', $card))->assertOk()->assertSee('Accueil · Nos boutiques · Carte Électroménager')->assertSee('site/images/soon/electro.jpg', false);

        $this->put(route('staff.contents.update', $card), ['title' => $card->title, 'description' => '', 'published' => 1, 'position' => 0,
            'image' => UploadedFile::fake()->image('electro.jpg', 64, 48), 'image_alt' => 'Cuisine équipée'])->assertSessionHasNoErrors();
        $card->refresh();
        $this->assertNotNull($card->media_asset_id);
        $this->get('/')->assertOk()->assertSee($card->media->url(), false)->assertDontSee('site/images/soon/electro.jpg', false);

        // Masquée : le site reprend l'image d'origine.
        $this->put(route('staff.contents.update', $card), ['title' => $card->title, 'description' => '', 'published' => 0, 'position' => 0])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('site/images/soon/electro.jpg', false);
        // Plusieurs visites de la page d'administration ne dupliquent pas les fiches.
        $this->get(route('staff.contents.index'))->assertOk();
        $this->assertSame(1, SiteContent::where('slot', 'boutique-electro')->count());
    }
}

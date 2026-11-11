<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Quote;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Site public V2 : boutiques Stores & Rideaux branchées sur l'espace pro. */
class SiteV2Test extends TestCase
{
    use RefreshDatabase;

    private function product(array $details = [], bool $published = true, string $ref = 'V2-STORE'): Product
    {
        return Product::create(['reference' => $ref, 'published' => $published, 'details' => $details + [
            'name' => 'Store enrouleur test', 'type' => 'store', 'category' => 'Stores enrouleurs', 'description' => 'Toile occultante',
            'universe' => 'stores_rideaux', 'available' => true, 'colors' => ['beige'], 'features' => ['occultant'],
        ]]);
    }

    public function test_home_keeps_three_tabs_with_upcoming_shops(): void
    {
        $this->seed();
        $this->get('/')->assertOk()
            ->assertSee('Accueil')->assertSee('MDS-Shop')->assertSee('MDS-Services')
            ->assertSee('Stores & Rideaux')->assertSee('Électroménager')->assertSee('Meubles')
            ->assertSee('Bientôt disponible')
            ->assertSee(url('/api/appointments'), false)
            ->assertSee('id="realisations"', false);
    }

    public function test_shops_show_only_published_stores_and_curtains(): void
    {
        $this->seed();
        $this->product();
        $this->product(['name' => 'Rideau caché', 'type' => 'rideau'], false, 'V2-HIDDEN');
        $this->product(['name' => 'Réfrigérateur', 'type' => 'electromenager', 'universe' => 'electromenager'], true, 'V2-ELECTRO');

        $this->get('/shop/stores')->assertOk()->assertSee('Store enrouleur test')->assertSee('Occultant');
        $this->get('/shop')->assertOk()->assertSee('Store enrouleur test')->assertDontSee('Rideau caché')->assertDontSee('Réfrigérateur');
        $this->get('/shop/electro')->assertNotFound();
        $this->get('/shop/stores?f[Couleur][]=Beige', ['X-Partial' => '1'])->assertOk()->assertJsonPath('total', 1);
        $this->get('/shop/stores?q=introuvable', ['X-Partial' => '1'])->assertOk()->assertJsonPath('total', 0);
    }

    public function test_product_page_links_to_quote_with_model(): void
    {
        $this->seed();
        $p = $this->product();
        $this->get('/produit/V2-STORE')->assertOk()->assertSee('Store enrouleur test')
            ->assertSee(route('quote', ['modele' => 'product-'.$p->id]), false);
        $this->get('/produit/V2-HIDDEN')->assertNotFound();
    }

    public function test_hidden_catalogue_closes_shop_pages_but_keeps_quote(): void
    {
        $this->seed();
        $this->product();
        SiteSetting::updateOrCreate(['key' => 'catalogue_visible'], ['enabled' => false]);
        $this->get('/shop/stores')->assertNotFound();
        $this->get('/produit/V2-STORE')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/shop/stores', false);
        $this->get('/devis')->assertOk();
    }

    public function test_quote_page_exposes_espace_pro_choices_and_api_accepts_them(): void
    {
        $this->seed();
        $p = $this->product();
        $this->get('/devis?modele=product-'.$p->id)->assertOk()
            ->assertSee('"preset":"product-'.$p->id.'"', false)
            ->assertSee(json_encode(route('quotes.stores')), false)
            ->assertSee(json_encode(route('quotes.curtains')), false);

        // Même charge utile que celle construite par public/site/js/mds.js.
        $this->postJson('/api/quotes/stores', [
            'contact' => ['civility' => 'mme', 'lastName' => 'Kouassi', 'firstName' => 'Aya', 'email' => null, 'phoneCountryCode' => '+225', 'phone' => '07 00 00 00 00', 'address' => 'Riviera 3', 'city' => 'Cocody', 'notes' => null],
            'product' => ['model' => 'product-'.$p->id, 'control' => 'motorisee', 'color' => 'beige', 'fabric' => 'store-lourd', 'finish' => 'store-standard', 'orderType' => 'installation'],
            'dimensions' => [['id' => 'f1', 'label' => 'Salon', 'width' => '180', 'height' => '220', 'quantity' => 1]],
            'acceptTerms' => true, 'submittedAt' => now()->toIso8601String(),
        ])->assertCreated();
        $this->assertSame('+2250700000000', Quote::firstOrFail()->contact['phone']);
    }

    public function test_legal_pages_render(): void
    {
        foreach (['mentions-legales', 'cgv', 'confidentialite', 'cookies'] as $page) {
            $this->get('/'.$page)->assertOk();
        }
    }
}

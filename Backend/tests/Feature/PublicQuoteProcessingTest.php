<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Traitement dans l'espace pro des devis reçus du site : choix du client relus et pré-remplis. */
class PublicQuoteProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create(['username' => (string) Str::uuid(), 'name' => 'Équipe', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => 'admin', 'must_change_password' => false]);
    }

    private function product(string $name, string $type, string $ref): Product
    {
        return Product::create(['reference' => $ref, 'published' => true, 'details' => ['name' => $name, 'type' => $type, 'available' => true]]);
    }

    private function contact(): array
    {
        return ['civility' => 'mme', 'lastName' => 'Kouassi', 'firstName' => 'Aya', 'email' => null, 'phoneCountryCode' => '+225', 'phone' => '0700000000', 'address' => 'Riviera 3', 'city' => 'Cocody', 'notes' => null];
    }

    private function id(string $code): int
    {
        return QuoteOption::where('code', $code)->value('id');
    }

    public function test_curtain_request_keeps_finish_first_and_prefills_the_staff_form(): void
    {
        $this->seed();
        $wave = $this->product('Rideaux Wave', 'rideau', 'RT-001');
        $this->postJson('/api/quotes/curtains', [
            'contact' => $this->contact(),
            'product' => ['model' => 'product-'.$wave->id, 'control' => 'manuelle', 'color' => 'beige', 'fabric' => 'occultant-100', 'sheer' => 'voilage-luxe', 'finish' => 'wave'],
            'dimensions' => [['id' => 'f1', 'label' => 'Salon', 'width' => '180', 'height' => '240', 'quantity' => 2]],
            'acceptTerms' => true,
        ])->assertCreated();
        $quote = Quote::firstOrFail();
        $this->assertSame('occultant-100', $quote->product['fabric_code']);
        $this->assertSame('voilage-luxe', $quote->product['sheer_code']);

        $this->actingAs($this->staff());
        $this->get(route('staff.quotes.show', $quote))->assertOk()
            ->assertSee('Choix du client sur le site')
            ->assertSeeInOrder(['Type de projet', 'Rideaux et voilages', 'Finition choisie', 'Wave', 'Modèle associé', 'Rideaux Wave', 'Tissu de rideau', 'Tissu occultant 100 %', 'Voilage', 'Voilage luxe', 'Manœuvre', 'Manuelle', 'Coloris', 'Beige', 'Fenêtres', 'Salon : 180 × 240 cm (×2)']);

        $form = $this->get(route('staff.quotes.edit', $quote))->assertOk();
        $form->assertSee('name="defaults[category]" data-value="rideau"', false)
            ->assertSee('name="defaults[motorized]" data-value="0"', false)
            ->assertSee('name="defaults[curtain_id]" data-value="'.$this->id('occultant-100').'"', false)
            ->assertSee('name="defaults[sheer_id]" data-value="'.$this->id('voilage-luxe').'"', false)
            // Wave + manœuvre manuelle → finition de chiffrage « Wave manuel ».
            ->assertSee('name="defaults[finish_id]" data-value="'.$this->id('wave-manuel').'"', false);
    }

    public function test_motorized_curtain_maps_wave_to_the_electric_finish(): void
    {
        $this->seed();
        $wave = $this->product('Rideaux Wave', 'rideau', 'RT-001');
        $this->postJson('/api/quotes/curtains', [
            'contact' => $this->contact(),
            'product' => ['model' => 'product-'.$wave->id, 'control' => 'motorisee', 'color' => 'gris', 'sheer' => 'voilage-luxe', 'finish' => 'wave'],
            'dimensions' => [['id' => 'f1', 'label' => 'Chambre', 'width' => '150', 'height' => '240', 'quantity' => 1]],
            'acceptTerms' => true,
        ])->assertCreated();
        $this->actingAs($this->staff())->get(route('staff.quotes.edit', Quote::firstOrFail()))->assertOk()
            ->assertSee('name="defaults[motorized]" data-value="1"', false)
            ->assertSee('name="defaults[finish_id]" data-value="'.$this->id('wave-electric').'"', false)
            ->assertSee('name="defaults[curtain_id]" data-value=""', false);
    }

    public function test_store_request_prefills_material_and_finish(): void
    {
        $this->seed();
        $zebra = $this->product('Store Zebra', 'store', 'ST-A001');
        $this->postJson('/api/quotes/stores', [
            'contact' => $this->contact(),
            'product' => ['model' => 'product-'.$zebra->id, 'control' => 'motorisee', 'color' => 'blanc', 'fabric' => 'store-lourd', 'finish' => 'store-standard', 'orderType' => 'remplacement'],
            'dimensions' => [['id' => 'f1', 'label' => 'Bureau', 'width' => '200', 'height' => '160', 'quantity' => 1]],
            'acceptTerms' => true,
        ])->assertCreated();
        $quote = Quote::firstOrFail();

        $this->actingAs($this->staff());
        $this->get(route('staff.quotes.show', $quote))->assertOk()
            ->assertSeeInOrder(['Type de projet', 'Store', 'Modèle choisi', 'Store Zebra', 'Toile', 'Store lourd', 'Finition', 'Finition standard', 'Prestation', 'Remplacement', 'Manœuvre', 'Motorisée']);
        $this->get(route('staff.quotes.edit', $quote))->assertOk()
            ->assertSee('name="defaults[category]" data-value="store"', false)
            ->assertSee('name="defaults[store_id]" data-value="'.$this->id('store-lourd').'"', false)
            ->assertSee('name="defaults[finish_id]" data-value="'.$this->id('store-standard').'"', false);
    }

    public function test_requests_saved_before_the_codes_are_matched_by_label(): void
    {
        $this->seed();
        // Format enregistré avant ce correctif : libellés seulement, tissu « Rideau + Voilage ».
        $quote = Quote::create(['type' => 'rideau', 'contact' => $this->contact(), 'dimensions' => [['label' => 'Salon', 'width' => 180, 'height' => 240, 'quantity' => 1]],
            'product' => ['model' => 'Rideaux Ruflette', 'model_id' => 22, 'control' => 'manuelle', 'color' => 'ecru', 'fabric' => 'Tissu occultant 80 % + Voilage transparent', 'sheer' => 'Voilage transparent', 'finish' => 'Ruflette', 'finish_code' => 'ruflette'],
            'terms_accepted_at' => now()]);

        $this->actingAs($this->staff());
        $this->get(route('staff.quotes.show', $quote))->assertOk()->assertSeeInOrder(['Finition choisie', 'Ruflette', 'Tissu de rideau', 'Tissu occultant 80 %', 'Voilage', 'Voilage transparent']);
        $this->get(route('staff.quotes.edit', $quote))->assertOk()
            ->assertSee('name="defaults[curtain_id]" data-value="'.$this->id('occultant-80').'"', false)
            ->assertSee('name="defaults[sheer_id]" data-value="'.$this->id('voilage-transparent').'"', false)
            ->assertSee('name="defaults[finish_id]" data-value="'.$this->id('ruflette-wave').'"', false);
    }
}

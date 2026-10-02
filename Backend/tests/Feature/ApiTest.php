<?php

namespace Tests\Feature;

use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function quote(): array
    {
        return [
            'contact' => ['civility' => 'mme', 'firstName' => 'Awa', 'lastName' => 'Kone', 'email' => 'awa@example.com', 'phone' => '+225 07 12 44 93 19', 'address' => 'Rue du marché', 'city' => 'Abidjan'],
            'product' => ['model' => 'enrouleur', 'mount' => 'murale', 'orderType' => 'installation', 'control' => 'manuelle', 'color' => 'beige', 'finish' => 'voile'],
            'dimensions' => [['id' => 'w1', 'label' => 'Salon', 'width' => '120,5', 'height' => 160, 'quantity' => 2]],
            'acceptTerms' => true,
        ];
    }

    private function appointment(): array
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-23 07:00:00', 'Africa/Abidjan'));
        return ['firstName' => 'Awa', 'lastName' => 'Kone', 'email' => 'awa@example.com', 'phone' => '+2250712449319', 'appointmentType' => 'showroom', 'date' => '2026-09-24', 'time' => '09:00'];
    }

    public function test_pages_render(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('csrf-token');
        $this->get('/devis')->assertOk();
    }

    public function test_catalogue_is_seeded_and_idempotent(): void
    {
        $this->seedProducts();
        $count = count(json_decode(file_get_contents(database_path('data/products.json')), true));
        $this->seedProducts();
        $this->assertDatabaseCount('products', $count);
        $this->getJson('/api/products')->assertOk()->assertJsonCount($count, 'data')->assertJsonPath('data.0.price', 'Prix');
    }

    public function test_store_quote_is_persisted_with_server_reference_and_normalized_dimensions(): void
    {
        $this->postJson('/api/quotes/stores', $this->quote())->assertCreated()->assertJsonStructure(['data' => ['reference', 'status']])->assertJsonMissingPath('data.contact');
        $quote = Quote::firstOrFail();
        $this->assertSame('store', $quote->type);
        $this->assertSame('AM-'.today()->toDateString().'-ST-112',$quote->reference);
        $this->assertEquals(120.5, $quote->dimensions[0]['width']);
        $this->assertNotNull($quote->terms_accepted_at);
    }

    public function test_curtain_quote_uses_its_own_choices(): void
    {
        $data = $this->quote();
        $data['product'] = ['model' => 'voilage', 'fabric' => 'lin-sauvage', 'color' => 'bleu', 'pattern' => 'uni', 'lining' => 'sans', 'finish' => 'oeillets', 'hanging' => 'tringle'];
        $this->postJson('/api/quotes/curtains', $data)->assertCreated();
        $this->assertDatabaseHas('quotes', ['type' => 'rideau']);
        $this->postJson('/api/quotes/stores', $data)->assertUnprocessable();
    }

    public function test_invalid_quote_is_not_saved(): void
    {
        $data = $this->quote();
        $data['acceptTerms'] = false;
        $data['dimensions'][0]['width'] = -1;
        $data['product']['control'] = 'motorisee';
        $data['contact']['email'] = 'invalid';
        $this->postJson('/api/quotes/stores', $data)->assertUnprocessable()->assertJsonValidationErrors(['acceptTerms', 'dimensions.0.width', 'product.powerSource', 'contact.email']);
        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_double_booking_is_rejected_and_availability_contains_no_personal_data(): void
    {
        $data = $this->appointment();
        $this->postJson('/api/appointments', $data)->assertCreated();
        $this->postJson('/api/appointments', $data)->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->assertDatabaseCount('appointments', 1);
        $this->getJson('/api/appointments/availability?date=2026-09-24')->assertOk()->assertExactJson(['data' => ['date' => '2026-09-24', 'timezone' => 'Africa/Abidjan', 'unavailable' => ['09:00']]]);
    }

    public function test_closed_past_and_distant_slots_are_rejected(): void
    {
        $data = $this->appointment();
        foreach (['2026-09-22', '2026-09-27', '2027-01-01'] as $date) {
            $this->postJson('/api/appointments', array_merge($data, ['date' => $date]))->assertUnprocessable()->assertJsonValidationErrors('time');
        }
        $this->postJson('/api/appointments', array_merge($data, ['time' => '12:00']))->assertUnprocessable();
        $this->postJson('/api/appointments', array_merge($data, ['date' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_submission_rate_is_limited(): void
    {
        for ($i = 0; $i < 10; $i++) $this->postJson('/api/quotes/stores', [])->assertUnprocessable();
        $this->postJson('/api/quotes/stores', [])->assertStatus(429);
    }

    public function test_csrf_is_required_outside_testing_mode(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/quotes/stores', $this->quote())->assertStatus(419);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        return User::create(['username' => 'Catalogue'.$role, 'name' => 'Catalogue', 'email' => $role.'@example.invalid', 'password' => 'TestPassword123!', 'role' => $role, 'must_change_password' => false]);
    }

    private function data(): array
    {
        return ['reference' => 'TEST-CATALOGUE', 'name' => 'Rideau test', 'type' => 'rideau', 'category' => 'Sur mesure', 'description' => 'Description test', 'quoteModel' => 'rideau-test',
            'published' => 1, 'available' => 1, 'featured' => 0, 'features' => ['occultant'], 'colors' => ['beige'], 'specs_text' => 'Tissu : lin',
            'material_cost' => 12500, 'material_cost_unit' => 'sqm', 'making_cost' => 2500, 'making_cost_unit' => 'metre',
            'delivery_cost' => 0, 'delivery_cost_unit' => 'flat', 'installation_cost' => 7500, 'installation_cost_unit' => 'item'];
    }

    public function test_costs_and_units_persist_on_creation_edit_and_reload(): void
    {
        $this->actingAs($this->staff())->post('/admin/products', $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        foreach (Product::COSTS as $key => $label) {
            $this->assertSame($this->data()[$key.'_cost'], $product->{$key.'_cost'});
            $this->assertSame($this->data()[$key.'_cost_unit'], $product->{$key.'_cost_unit'});
        }
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertSee('value="12500"', false)->assertSee('value="sqm" selected', false);
        $this->put('/admin/products/'.$product->id, [...$this->data(), 'material_cost' => 13000, 'making_cost' => null, 'published' => 0, 'available' => 0])->assertSessionHasNoErrors();
        $fresh = $product->fresh();
        $this->assertSame(13000, $fresh->material_cost);
        $this->assertNull($fresh->making_cost);
        $this->assertSame(0, $fresh->delivery_cost);
        $this->assertFalse($fresh->published);
        $this->assertFalse($fresh->details['available']);
        $this->get('/admin/products')->assertOk()->assertSee('13 000 FCFA')->assertSee('Non renseigné')->assertSee('0 FCFA');
    }

    public function test_quick_status_changes_preserve_costs_and_public_api_never_contains_costs(): void
    {
        $this->actingAs($this->staff())->post('/admin/products', $this->data())->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->patch('/admin/products/'.$product->id.'/visibility', ['published' => 1, 'available' => 0, 'material_cost' => 1])->assertSessionHasNoErrors();
        $this->assertSame(12500, $product->fresh()->material_cost);
        $this->assertSame(['occultant'], $product->fresh()->details['features']);
        $response = $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.available', false);
        foreach (Product::COSTS as $key => $label) {
            $response->assertJsonMissingPath('data.0.'.$key.'_cost')->assertJsonMissingPath('data.0.'.$key.'_cost_unit');
            $this->assertArrayNotHasKey($key.'_cost', $product->toArray());
        }
        $this->patch('/admin/products/'.$product->id.'/visibility', ['published' => 0, 'available' => 1])->assertSessionHasNoErrors();
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_invalid_costs_units_and_flags_never_partially_save(): void
    {
        $this->actingAs($this->staff())->post('/admin/products', $this->data())->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        foreach ([['material_cost' => -1], ['making_cost' => '1.5'], ['delivery_cost' => 100000001], ['installation_cost_unit' => 'invalid'], ['material_cost_unit' => null], ['published' => 'yes']] as $invalid) {
            $this->putJson('/admin/products/'.$product->id, [...$this->data(), ...$invalid])->assertUnprocessable();
            $this->assertSame($this->data()['material_cost'], $product->fresh()->material_cost);
            $this->assertSame($this->data()['making_cost'], $product->fresh()->making_cost);
        }
        $this->patchJson('/admin/products/'.$product->id.'/visibility', ['published' => 0])->assertUnprocessable();
        $this->assertTrue($product->fresh()->published);
    }

    public function test_agents_and_guests_cannot_change_catalogue(): void
    {
        $product = Product::create(['reference' => 'PROTECTED', 'details' => ['available' => true]]);
        $this->post('/admin/products', $this->data())->assertRedirect('/admin/connexion');
        $this->actingAs($this->staff('agent'));
        $this->post('/admin/products', $this->data())->assertForbidden();
        $this->put('/admin/products/'.$product->id, $this->data())->assertForbidden();
        $this->patch('/admin/products/'.$product->id.'/visibility', ['published' => 0, 'available' => 0])->assertForbidden();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_filters_and_reseeding_preserve_catalogue_changes(): void
    {
        $this->seed();
        $product = Product::firstOrFail();
        $product->update(['material_cost' => 32100, 'material_cost_unit' => 'sqm', 'published' => false, 'details' => [...$product->details, 'available' => false]]);
        $this->seed();
        $this->assertSame(32100, $product->fresh()->material_cost);
        $this->assertFalse($product->fresh()->published);
        $this->actingAs($this->staff());
        $this->get('/admin/products?'.http_build_query(['q' => $product->reference, 'published' => 0, 'available' => 0, 'type' => $product->details['type']]))->assertOk()->assertSee('32 100 FCFA');
        $this->get('/admin/products?'.http_build_query(['q' => $product->reference, 'available' => 1]))->assertOk()->assertSee('Aucun produit');
    }

    public function test_global_visibility_hides_public_catalogue_and_restores_individual_choices(): void
    {
        $this->withoutVite();
        $this->seed();
        $hidden = Product::firstOrFail();
        $hidden->update(['published' => false]);
        $snapshot = Product::orderBy('id')->get()->toJson();
        $visibleCount = Product::where('published', true)->count();
        $this->get('/')->assertOk()->assertSee('id="catalogue"', false);
        $this->getJson('/api/products')->assertJsonCount($visibleCount, 'data');

        $this->actingAs($this->staff())->get('/admin/products')->assertOk()->assertSee('Masquer le catalogue');
        $this->patch('/admin/catalogue/visibilite', ['visible' => 0])->assertRedirect('/admin/products')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_settings', ['key' => 'catalogue_visible', 'enabled' => false]);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalogue_masque']);
        $this->get('/')->assertOk()->assertDontSee('id="catalogue', false)->assertDontSee('data-featured=', false)->assertDontSee('#catalogue', false)->assertSee('id="realisations"', false);
        $this->get('/devis')->assertOk()->assertDontSee('#catalogue', false);
        $this->getJson('/api/products')->assertExactJson(['data' => []]);
        $this->get('/admin/products')->assertOk()->assertSee('Afficher le catalogue')->assertSee('Masqué avec le catalogue');
        $this->get('/admin/devis/nouveau')->assertOk()->assertSee($hidden->reference);
        $this->assertSame($snapshot, Product::orderBy('id')->get()->toJson());

        $this->seed();
        $this->getJson('/api/products')->assertExactJson(['data' => []]);
        $this->patch('/admin/catalogue/visibilite', ['visible' => 1])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('id="catalogue"', false);
        $this->get('/devis')->assertOk()->assertSee('#catalogue', false);
        $this->getJson('/api/products')->assertJsonCount($visibleCount, 'data');
        $this->assertFalse($hidden->fresh()->published);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalogue_affiche']);
    }

    public function test_global_visibility_is_admin_only(): void
    {
        $this->patch('/admin/catalogue/visibilite', ['visible' => 0])->assertRedirect('/admin/connexion');
        $this->actingAs($this->staff('agent'))->patch('/admin/catalogue/visibilite', ['visible' => 0])->assertForbidden();
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_global_visibility_validates_input(): void
    {
        $this->actingAs($this->staff());
        $this->patchJson('/admin/catalogue/visibilite', [])->assertUnprocessable();
        $this->patchJson('/admin/catalogue/visibilite', ['visible' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('site_settings', 0);
    }
}

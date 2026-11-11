<?php

namespace Tests\Feature;

use App\Models\QuoteOption;
use App\Models\User;
use App\Services\Quotes\PricingConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Matériaux et finitions : configuration du chiffrage (calcul, coefficient, base si quantité vide). */
class QuoteOptionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => (string) Str::uuid(), 'name' => 'Admin', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => 'admin', 'must_change_password' => false]);
    }

    private function option(string $code): QuoteOption
    {
        return QuoteOption::where('code', $code)->firstOrFail();
    }

    private function form(QuoteOption $o, array $extra): array
    {
        return ['tariff_form' => 1, 'open_form' => (string) $o->id, 'kind' => $o->kind, 'category' => $o->category, 'label' => $o->label, 'unit_price' => $o->unit_price, 'unit' => $o->unit,
            'fabric_group' => $o->fabric_group, 'motorization' => $o->motorization, 'active' => 1, 'public_choice' => (int) $o->public_choice, 'finish_codes' => $o->finish_codes ?? [], ...$extra];
    }

    public function test_settings_are_saved_per_kind_and_shown_in_the_list(): void
    {
        $this->seed();
        $this->actingAs($this->admin());
        $fabric = $this->option('occultant-80');
        $this->put(route('staff.quote-options.update', $fabric), $this->form($fabric, ['calc_mode' => 'coefficient', 'calc_coefficient' => '2,8', 'calc_basis' => 'all_fabrics']))->assertSessionHasNoErrors();
        $fabric->refresh();
        $this->assertSame('coefficient', $fabric->calc_mode);
        $this->assertSame('2.8', $fabric->calcCoefficient());
        $this->assertNull($fabric->calc_basis, 'La base ne concerne pas les tissus.');

        $sewing = $this->option('confection');
        $this->put(route('staff.quote-options.update', $sewing), $this->form($sewing, ['calc_mode' => 'fixed', 'calc_basis' => 'all_fabrics', 'calc_coefficient' => '3']))->assertSessionHasNoErrors();
        $sewing->refresh();
        $this->assertSame(['fixed', null, 'all_fabrics'], [$sewing->calc_mode, $sewing->calcCoefficient(), $sewing->calc_basis]);

        $rail = $this->option('rail-simple');
        $this->put(route('staff.quote-options.update', $rail), $this->form($rail, ['calc_mode' => 'fixed']))->assertSessionHasErrors('calc_mode');

        $finish = $this->option('wave-manuel');
        $this->put(route('staff.quote-options.update', $finish), $this->form($finish, ['calc_mode' => 'coefficient', 'calc_coefficient' => '2']))->assertSessionHasErrors('calc_mode');
        $this->put(route('staff.quote-options.update', $finish), $this->form($finish, ['calc_coefficient' => '2']))->assertSessionHasNoErrors();
        $this->assertNull($finish->fresh()->calc_coefficient, 'Une finition n’a pas de configuration de chiffrage.');

        $this->get(route('staff.quote-options.index'))->assertOk()
            ->assertSee('Chiffrage dans les devis')->assertSee('Base si quantité vide')
            ->assertSee('Chiffrage : Largeur × coefficient, × 2,8')
            ->assertSee('Chiffrage : Au forfait, Tissu principal + voilage');
    }

    public function test_partial_update_without_the_fields_keeps_the_settings(): void
    {
        $this->seed();
        $rail = $this->option('rail-simple');
        $rail->update(['calc_mode' => 'coefficient', 'calc_coefficient' => '1.2', 'calc_basis' => 'primary']);
        $this->actingAs($this->admin());
        $data = $this->form($rail, []);
        unset($data['tariff_form']);
        $this->put(route('staff.quote-options.update', $rail), $data)->assertSessionHasNoErrors();
        $this->assertSame(['coefficient', '1.2', 'primary'], [$rail->fresh()->calc_mode, $rail->fresh()->calcCoefficient(), $rail->fresh()->calc_basis]);
    }

    public function test_quote_calculation_uses_the_option_settings_when_the_quantity_is_empty(): void
    {
        $this->seed();
        $this->actingAs($this->admin());
        $this->option('occultant-80')->update(['calc_mode' => 'coefficient', 'calc_coefficient' => '3']);
        $this->option('rail-simple')->update(['calc_mode' => 'coefficient', 'calc_coefficient' => '1.2', 'calc_basis' => 'primary']);

        $s = ['category' => 'rideau', 'curtain_id' => $this->option('occultant-80')->id, 'sheer_id' => null, 'store_id' => null, 'finish_id' => $this->option('reflete-manuel')->id, 'motorized' => 0];
        $fees = [];
        foreach (PricingConfig::FEES as $key => $label) {
            $fees[$key] = ['enabled' => 0, 'unit_price' => 0, 'scope' => 'global', 'quantity' => 1];
        }
        // Quantités vides, aucun mode ni coefficient envoyés : la configuration des options s'applique.
        $window = [...$s, 'inherit_defaults' => 1, 'label' => 'Fenêtre 1', 'width' => '200', 'height' => '210', 'quantity' => 1,
            'costing' => ['zone' => 'abidjan', 'support' => ['option_id' => $this->option('rail-simple')->id], 'tape' => ['option_id' => $this->option('reflete-4')->id], 'sewing' => ['option_id' => $this->option('confection')->id]]];
        $result = $this->postJson('/admin/devis/chiffrer', ['defaults' => $s, 'windows' => [$window],
            'pricing' => ['engine' => 2, 'display_mode' => 'grouped', 'discount_type' => 'none', 'discount_value' => 0, 'deposit_percent' => 70, 'fees' => $fees],
            'tax_rate' => 0, 'submission_token' => (string) Str::uuid(), 'receipt_token' => (string) Str::uuid(), 'edit_version' => 0,
            'contact' => ['lastName' => 'Client test', 'phone' => '+2250700000000', 'address' => 'Abidjan'], 'quote_date' => today()->toDateString(), 'valid_until' => today()->addDays(30)->toDateString(),
            'use_catalogue' => 1, 'payment_mode' => 'advance', 'deposit_amount' => 0])->assertOk()->json('financial.openings.0.lines');
        $quantity = fn (string $key) => (float) collect($result)->firstWhere('key', $key)['quantity'];

        $this->assertEqualsWithDelta(6.0, $quantity('material'), 0.0001, 'Tissu : 2 m × 3.');
        $this->assertEqualsWithDelta(2.4, $quantity('support'), 0.0001, 'Rail : 2 m × 1,2, tissu principal seul.');
        $this->assertEqualsWithDelta(2.0, $quantity('tape'), 0.0001, 'Reflète sans configuration : réglage habituel (largeur × 1).');
    }
}

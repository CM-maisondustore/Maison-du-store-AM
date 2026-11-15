<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DocumentBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Correctifs espace pro : ordre des lignes d'une désignation, pastilles « Choix communs / Personnalisé », relevé agent en pastilles, « + » flottant du bureau. */
class EspaceProCorrectifsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        return User::create(['username' => (string) Str::uuid(), 'name' => ucfirst($role), 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => $role, 'must_change_password' => false]);
    }

    public function test_table_lines_of_each_opening_follow_the_chosen_order_only_when_set(): void
    {
        $lines = [['key' => 'material', 'description' => 'Tissu'], ['key' => 'support', 'description' => 'Rail'], ['description' => 'Ligne libre'], ['key' => 'secondary_material', 'description' => 'Voilage'], ['key' => 'installation', 'description' => 'Pose']];
        // Sans réglage : l'ordre du chiffrage est conservé.
        $this->assertSame(['Tissu', 'Rail', 'Ligne libre', 'Voilage', 'Pose'], array_column(DocumentBlocks::orderLines($lines, []), 'description'));
        // Ordre choisi : tissu, voilage, rail ; les natures non listées suivent dans l'ordre du chiffrage.
        $this->assertSame(['Tissu', 'Voilage', 'Rail', 'Ligne libre', 'Pose'], array_column(DocumentBlocks::orderLines($lines, ['material', 'secondary_material', 'support']), 'description'));
        // « Autres lignes » peut être placé explicitement.
        $this->assertSame(['Ligne libre', 'Tissu', 'Rail', 'Voilage', 'Pose'], array_column(DocumentBlocks::orderLines($lines, ['other']), 'description'));

        $block = ['type' => 'lines', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 100, 'pages' => ['single']];
        $items = DocumentBlocks::normalize(json_encode(['items' => [[...$block, 'line_order' => ['support', 'inconnu', 'material']], $block]]));
        $this->assertSame(['support', 'material'], $items['items'][0]['line_order']);
        $this->assertArrayNotHasKey('line_order', $items['items'][1]);

        $vm = ['opening_groups' => ['' => [['label' => 'Salon', 'dimensions' => 'L 2 m × H 2,4 m', 'quantity' => 1, 'subtotal' => 1000, 'lines' => $lines]]], 'totals' => []];
        $ordered = array_column(array_filter(DocumentBlocks::rows($vm, [...$items['items'][0], 'group' => true, 'row_order' => ['designations']]), fn ($r) => $r['kind'] === 'line'), 'label');
        $this->assertSame(['Rail', 'Tissu', 'Ligne libre', 'Voilage', 'Pose'], $ordered);
    }

    public function test_discount_percent_and_fees_with_totals_are_opt_in(): void
    {
        $lines = [['key' => 'material', 'description' => 'Tissu', 'displayAmount' => 60000], ['key' => 'installation', 'description' => 'Pose', 'displayAmount' => 15000]];
        $vm = ['opening_groups' => ['' => [
            ['label' => 'Salon', 'dimensions' => 'L 2 m × H 2,4 m', 'quantity' => 1, 'subtotal' => 75000, 'lines' => $lines],
            ['label' => 'Chambre', 'dimensions' => 'L 1 m × H 2 m', 'quantity' => 1, 'subtotal' => 35000, 'lines' => [['key' => 'material', 'description' => 'Tissu', 'displayAmount' => 25000], ['key' => 'installation', 'description' => 'Pose', 'displayAmount' => 10000]]],
        ]], 'fee_rows' => [['label' => 'Livraison', 'displayQuantity' => '1', 'unitPrice' => 5000, 'displayAmount' => 5000]],
            'totals' => ['subtotal' => 115000, 'tax' => 0, 'grossTotal' => 115000, 'discount' => ['type' => 'fixed', 'value' => 11500], 'discountAmount' => 11500]];
        $block = ['type' => 'lines', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 100, 'pages' => ['single']];
        $items = DocumentBlocks::normalize(json_encode(['items' => [$block, [...$block, 'discount_percent' => true, 'fees_with_totals' => true]]]))['items'];
        $this->assertArrayNotHasKey('fees_with_totals', $items[0]);
        $this->assertArrayNotHasKey('discount_percent', $items[0]);

        // Sans option : rien ne change.
        $plain = DocumentBlocks::rows($vm, $items[0]);
        $this->assertContains('Pose', array_column($plain, 'label'));
        $this->assertContains('Remise commerciale', array_column($plain, 'label'));

        // Avec les options : la pose quitte les ouvertures (sous-totaux corrigés) et rejoint la livraison, juste avant la remise en %.
        $rows = DocumentBlocks::rows($vm, $items[1]);
        $sums = array_values(array_filter($rows, fn ($r) => $r['kind'] === 'sum'));
        $this->assertSame(['Pose', 'Livraison', 'Sous-total', 'Remise commerciale (10 %)'], array_column($sums, 'label'));
        $this->assertSame(['25 000', '5 000', '115 000', '−11 500'], array_column($sums, 'amount'));
        $this->assertSame(['Tissu', 'Tissu'], array_column(array_filter($rows, fn ($r) => $r['kind'] === 'line'), 'label'));
        $this->assertSame(['60 000', '25 000'], array_column(array_filter($rows, fn ($r) => $r['kind'] === 'subtotal'), 'amount'));
        $this->assertSame('12,5', DocumentBlocks::discountPercent(['discount' => ['type' => 'percentage', 'value' => '12.5'], 'discountAmount' => 1, 'grossTotal' => 1]));
    }

    public function test_layout_editor_offers_the_line_order_list(): void
    {
        $this->actingAs($this->user('admin'))->get(route('staff.layouts.edit'))->assertOk()
            ->assertSee('data-line-order', false)->assertSee('Ordre des lignes de chaque désignation')
            ->assertSee('data-prop="fees_with_totals"', false)->assertSee('data-prop="discount_percent"', false)->assertSee('"lineKinds":{"material":', false);
    }

    public function test_office_quote_form_uses_common_or_custom_pills_per_opening(): void
    {
        $this->actingAs($this->user('manager'))->get(route('staff.quotes.create'))->assertOk()
            ->assertSee('data-inherit-mode="1"', false)->assertSee('data-inherit-mode="0"', false)->assertSee('Personnalisé')->assertSee('data-inherit name="windows[0][inherit_defaults]"', false);
    }

    public function test_agent_survey_uses_pills_for_every_choice_but_the_finish(): void
    {
        $html = $this->actingAs($this->user('agent'))->get(route('staff.quotes.create'))->assertOk()->getContent();
        foreach (['project_type', 'motorized', 'zone'] as $key) $this->assertMatchesRegularExpression('/data-agent-choice="'.$key.'" data-chips/', $html, $key);
        $this->assertStringContainsString('data-agent-part="curtain" data-chips', $html);
        $this->assertStringContainsString('data-agent-part="sheer" data-chips', $html);
        $this->assertDoesNotMatchRegularExpression('/data-agent-choice="finish_id"[^>]*data-chips/', $html, 'La finition garde son menu.');
        $this->assertStringContainsString('<span class="choice-name" id="defaults-type">Type de projet</span>', $html);
    }

    public function test_office_roles_get_a_floating_new_button_and_agents_keep_theirs(): void
    {
        foreach (['manager', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get(route('staff.dashboard'))->assertOk()
                ->assertSee('data-open-dialog="new-sheet"', false)->assertSee('id="new-sheet"', false)->assertSee(route('staff.messages.create'), false);
        }
        $this->actingAs($this->user('agent'))->get(route('staff.dashboard'))->assertOk()
            ->assertDontSee('new-sheet')->assertSee('Nouveau devis sur place');
    }

    public function test_confection_tariffs_can_be_tied_to_finishes_under_the_category(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $sewing = \App\Models\QuoteOption::where('code', 'confection')->firstOrFail();
        $form = ['tariff_form' => 1, 'open_form' => (string) $sewing->id, 'kind' => 'sewing', 'category' => 'rideau', 'label' => 'Confection œillets', 'unit_price' => 1500, 'unit' => 'm',
            'motorization' => 'any', 'active' => 1, 'public_choice' => 0, 'finish_codes' => ['wave-manuel']];
        $this->actingAs($admin)->put(route('staff.quote-options.update', $sewing), $form)->assertSessionHasNoErrors();
        $this->assertSame(['wave-manuel'], $sewing->fresh()->finish_codes);
        // Une finition d'une autre catégorie est refusée.
        $this->actingAs($admin)->put(route('staff.quote-options.update', $sewing), [...$form, 'finish_codes' => ['store-standard']])->assertSessionHasErrors('finish_codes.0');
        // Le formulaire propose les finitions sous la catégorie, pour la confection aussi ; la liste rappelle les finitions choisies.
        $page = $this->actingAs($admin)->get(route('staff.quote-options.index'))->assertOk();
        $page->assertSee('data-for-kinds="support tape hooks sewing"', false)->assertSee('Finitions concernées')->assertSee('Finitions : Wave manuel');
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'name="label"'), strpos($html, 'Finitions concernées'), 'Le choix des finitions suit la catégorie, avant le libellé.');
    }
}

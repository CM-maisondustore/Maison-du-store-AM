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

    public function test_layout_editor_offers_the_line_order_list(): void
    {
        $this->actingAs($this->user('admin'))->get(route('staff.layouts.edit'))->assertOk()
            ->assertSee('data-line-order', false)->assertSee('Ordre des lignes de chaque désignation')->assertSee('"lineKinds":{"material":', false);
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
}

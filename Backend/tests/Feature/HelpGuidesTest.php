<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StaffInterface;
use App\Support\Help\HelpGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Page Aide : guides par rôle, bouton « ? » de chaque page, chaque module du menu documenté. */
class HelpGuidesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        // Nouvel utilisateur, nouvelle session : la protection de session déconnecte sinon le compte suivant.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        return User::create(['username' => (string) Str::uuid(), 'name' => ucfirst($role).' Test', 'email' => Str::random(8).'@example.invalid', 'password' => 'TestPassword123!', 'role' => $role, 'must_change_password' => false]);
    }

    public function test_every_guide_is_complete_and_its_links_and_captures_exist(): void
    {
        $all = HelpGuides::all();
        $this->assertGreaterThanOrEqual(15, count($all));
        $captures = json_decode(file_get_contents(resource_path('help/captures.json')), true, flags: JSON_THROW_ON_ERROR);
        $names = array_column($captures['captures'], 'name');
        foreach ($all as $slug => $guide) {
            $this->assertNotSame('', $guide['summary'], "$slug : résumé manquant");
            $this->assertNotEmpty($guide['roles'], "$slug : rôles manquants");
            $this->assertContains($guide['group'], HelpGuides::GROUPS, "$slug : rubrique inconnue");
            preg_match_all('/\(guide:([a-z0-9-]+)/', $guide['body'], $links);
            foreach ($links[1] as $target) {
                $this->assertArrayHasKey($target, $all, "$slug : lien vers un guide inexistant ($target)");
                // Un lien ne mène qu'à un guide que les mêmes lecteurs peuvent ouvrir.
                $this->assertEmpty(array_diff($guide['roles'], $all[$target]['roles']), "$slug → $target : guide non visible pour tous les lecteurs");
            }
            preg_match_all('/\(capture:([a-z0-9-]+)\)/', $guide['body'], $shots);
            foreach ($shots[1] as $name) $this->assertContains($name, $names, "$slug : capture « $name » absente de captures.json");
        }
    }

    public function test_each_role_sees_only_its_guides(): void
    {
        foreach (['agent', 'manager', 'admin'] as $role) {
            $user = $this->user($role);
            $visible = HelpGuides::forUser($user);
            $this->assertNotEmpty($visible);
            $this->actingAs($user)->get(route('staff.help.index'))->assertOk()->assertSee('Rechercher dans les guides');
            foreach (HelpGuides::all() as $slug => $guide) {
                $response = $this->actingAs($user)->get(route('staff.help.show', $slug));
                in_array($role, $guide['roles'], true) ? $response->assertOk()->assertSee($guide['title']) : $response->assertNotFound();
            }
        }
        $agent = $this->user('agent');
        $this->actingAs($agent)->get(route('staff.help.index'))->assertSee('Réaliser une prestation')->assertDontSee('Gérer l’équipe')->assertDontSee('Établir et envoyer une facture');
    }

    public function test_every_menu_module_has_a_guide_for_its_role(): void
    {
        foreach (['agent', 'manager', 'admin'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            $nav = StaffInterface::navigation($user);
            $items = collect($nav['sections'])->pluck('items')->flatten(1)->merge($nav['tabs']);
            foreach ($items as $item) {
                $name = app('router')->getRoutes()->match(Request::create($item['href']))->getName();
                $this->assertNotNull(HelpGuides::forRoute($user, $name), "$role : aucun guide pour $name ({$item['label']})");
            }
        }
    }

    public function test_question_mark_opens_the_guide_of_the_current_page(): void
    {
        $manager = $this->user('manager');
        $response = $this->actingAs($manager)->get(route('staff.quotes.index'));
        $this->assertSame(200, $response->status(), (string) $response->headers->get('Location'));
        $response
            ->assertSee(route('staff.help.show', 'demande-devis'), false)->assertSee('Aide sur cette page : Traiter une demande de devis');
        $agent = $this->user('agent');
        $response = $this->actingAs($agent)->get(route('staff.dashboard'));
        $this->assertSame(200, $response->status(), (string) $response->headers->get('Location'));
        $response
            ->assertSee(route('staff.help.show', 'prise-en-main-agent'), false)->assertSee(route('staff.help.index'), false);
    }

    public function test_guide_markdown_is_rendered_safely(): void
    {
        $guide = HelpGuides::parse('essai', "---\ntitle: Essai\nsummary: Résumé\nroles: admin\n---\n1. Étape\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))\n\n![Absente](capture:inexistante)\n\n> **Bon à savoir.** Note");
        $html = HelpGuides::html($guide);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('inexistante', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('class="help-note"', $html);
    }
}

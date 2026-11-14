<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Visibilité du site pour les moteurs et les assistants IA : robots, sitemap, llms.txt, Markdown, données structurées, pages À propos / Contact. */
class SiteAiVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create(['reference' => 'ST-AI1', 'published' => true, 'details' => [
            'name' => 'Store Zebra', 'type' => 'store', 'category' => 'Stores enrouleurs', 'description' => 'Toile jour-nuit',
            'universe' => 'stores_rideaux', 'available' => true, 'colors' => ['beige'], 'features' => ['occultant'],
        ]]);
    }

    public function test_robots_declares_content_signals_and_sitemap_only_when_indexed(): void
    {
        config(['deployment.indexing' => true]);
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Content-Signal: search=yes, ai-input=yes, ai-train=no')
            ->assertSee('Sitemap: '.url('/sitemap.xml'))->assertSee('Disallow: /admin');
        config(['deployment.indexing' => false]);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    public function test_sitemap_lists_pages_and_products_with_lastmod(): void
    {
        $this->seed();
        $this->product();
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $all = [];
        foreach ($doc->url as $u) $all[(string) $u->loc] = (string) $u->lastmod;
        foreach ([route('home'), route('quote'), route('site.info', 'contact'), route('site.info', 'a-propos'), route('site.legal', 'mentions-legales'), route('site.product', 'ST-AI1')] as $url) {
            $this->assertArrayHasKey($url, $all, $url);
        }
        $this->assertSame(now()->toDateString(), $all[route('site.product', 'ST-AI1')]);
        $this->assertSame('2026-11-11', $all[route('site.legal', 'mentions-legales')]);
        $this->assertSame(now()->toDateString(), $all[route('home')], 'L’accueil suit le dernier contenu ou produit modifié.');
    }

    public function test_llms_txt_and_markdown_for_agents(): void
    {
        $this->seed();
        $this->product();
        $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Maison du Store AM')->assertSee('[Store Zebra]('.route('site.product', 'ST-AI1').')', false)
            ->assertSee('[Mentions légales]('.route('site.legal', 'mentions-legales').')', false);

        // Un navigateur garde la page HTML, annoncée par l'en-tête Link ; un agent qui demande du Markdown le reçoit.
        $this->get('/', ['Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('Link')->assertSee('application/ld+json', false);
        $this->assertStringContainsString('rel="describedby"', $this->get('/')->headers->get('Link'));
        $this->get('/', ['Accept' => 'text/markdown, text/html;q=0.5'])->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertSee('# Maison du Store AM');
        $this->get(route('site.product', 'ST-AI1'), ['Accept' => 'text/markdown'])->assertOk()
            ->assertSee('# Store Zebra')->assertSee('Référence : ST-AI1');
    }

    public function test_pages_carry_organisation_store_and_breadcrumb_data(): void
    {
        $this->seed();
        $this->product();
        $html = $this->get('/')->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
        $graph = json_decode($m[1][0], true, flags: JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame(['Organization', 'HomeGoodsStore', 'WebSite'], array_column($graph, '@type'));
        $this->assertSame('Abidjan', $graph[1]['address']['addressLocality']);
        $this->assertArrayNotHasKey('sameAs', $graph[0], 'Sans profil renseigné, pas de sameAs vide.');
        $this->assertStringContainsString('property="og:title"', $html);

        config(['site.profiles' => ['https://www.facebook.com/maisondustoream']]);
        $html = $this->get(route('site.product', 'ST-AI1'))->assertOk()->assertSee('BreadcrumbList')->getContent();
        $this->assertStringContainsString('"sameAs":["https://www.facebook.com/maisondustoream"]', $html);
    }

    public function test_about_and_contact_pages_are_linked_from_the_footer(): void
    {
        $this->seed();
        $this->get(route('site.info', 'contact'))->assertOk()->assertSee('Nous contacter')->assertSee(config('site.contact.email'));
        $this->get(route('site.info', 'a-propos'))->assertOk()->assertSee('Votre projet, en quatre étapes');
        $this->get('/')->assertOk()
            ->assertSee('href="'.route('site.info', 'a-propos').'"', false)
            ->assertSee('href="'.route('site.info', 'contact').'"', false)
            ->assertSee('href="'.route('site.legal', 'mentions-legales').'"', false);
    }
}

<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_consenting_visit_records_aggregate_technical_data_without_ip(): void
    {
        $response = $this->postJson('/api/analytics/collect', [
            'visitor_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(),
            'event' => 'page_view', 'path' => '/devis?email=private@example.com', 'title' => 'Devis',
        ]);
        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('site_visits', ['current_path' => '/devis', 'page_views' => 1, 'device' => 'ordinateur']);
        $this->assertDatabaseHas('site_visit_events', ['name' => 'page_view', 'path' => '/devis']);
        $this->assertNotContains('ip_address', \Illuminate\Support\Facades\Schema::getColumnListing('site_visits'));
    }

    public function test_origin_comes_from_the_page_referrer_and_survives_an_expired_session(): void
    {
        // Requête sans jeton CSRF (page restée ouverte, session expirée) : la mesure anonyme est acceptée.
        $this->withMiddleware()->post('/api/analytics/collect', [
            'visitor_id' => (string) Str::uuid(), 'session_id' => $session = (string) Str::uuid(),
            'event' => 'page_view', 'path' => '/', 'referrer' => 'l.facebook.com',
        ], ['Referer' => 'http://localhost/', 'Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseHas('site_visits', ['origin' => 'facebook_instagram', 'referrer_host' => 'l.facebook.com']);
        $this->postJson('/api/analytics/collect', ['visitor_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(), 'event' => 'page_view', 'path' => '/', 'referrer' => 'www.google.com'])->assertOk();
        $this->postJson('/api/analytics/collect', ['visitor_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(), 'event' => 'page_view', 'path' => '/', 'referrer' => 'maisondustoream.com'])->assertOk();
        $this->assertSame(['direct' => 1, 'facebook_instagram' => 1, 'google' => 1], \App\Models\SiteVisit::pluck('origin')->countBy()->sortKeys()->all());
    }

    public function test_visitors_dashboard_is_admin_only(): void
    {
        $agent = User::create(['username' => Str::random(10), 'name' => 'Agent', 'email' => Str::random(10).'@example.com', 'password' => 'TemporaryPass123!', 'role' => 'agent', 'must_change_password' => false]);
        $this->actingAs($agent)->get('/admin/visiteurs')->assertForbidden();
        $admin = User::create(['username' => Str::random(10), 'name' => 'Admin', 'email' => Str::random(10).'@example.com', 'password' => 'TemporaryPass123!', 'role' => 'admin', 'must_change_password' => false]);
        $admin->forceFill(['must_change_password' => false])->save();
        $this->flushSession();
        $this->actingAs($admin)->get('/admin/visiteurs')->assertOk()->assertSee('Visiteurs en temps réel')->assertSee('Les adresses IP brutes ne sont pas enregistrées');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_checks_the_database_schema_without_exposing_errors(): void
    {
        $this->getJson('/health/ready')->assertOk()->assertExactJson(['status' => 'ok']);
        DB::shouldReceive('table')->andThrow(new \RuntimeException('Connection unavailable'));
        $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_staging_is_not_indexable_and_has_response_headers(): void
    {
        config(['deployment.indexing' => false]);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_production_can_be_indexed(): void
    {
        config(['deployment.indexing' => true]);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertHeaderMissing('X-Robots-Tag');
    }
}

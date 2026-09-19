<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeploymentRecord;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_1_health_endpoint_returns_system_health_diagnostics_without_secrets(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.database.connected', true);

        $content = $response->getContent();

        $this->assertStringNotContainsString('consumer_secret', $content);
        $this->assertStringNotContainsString('api_secret', $content);
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
    }

    public function test_2_health_check_service_checks_all_core_components(): void
    {
        /** @var HealthCheckService $service */
        $service = app(HealthCheckService::class);
        $health = $service->checkSystemHealth();

        $this->assertArrayHasKey('app', $health);
        $this->assertArrayHasKey('database', $health);
        $this->assertArrayHasKey('redis', $health);
        $this->assertArrayHasKey('storage', $health);
        $this->assertArrayHasKey('queue', $health);
        $this->assertArrayHasKey('livekit', $health);
        $this->assertArrayHasKey('mpesa', $health);
        $this->assertTrue($health['database']['connected']);
    }

    public function test_3_deployment_service_executes_whitelisted_actions_and_logs_audit_records(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        /** @var DeploymentService $service */
        $service = app(DeploymentService::class);

        $record = $service->executeAction('health_check', $admin, '192.168.1.10');

        $this->assertInstanceOf(DeploymentRecord::class, $record);
        $this->assertEquals('health_check', $record->action);
        $this->assertEquals('success', $record->status);
        $this->assertEquals($admin->id, $record->initiated_by_user_id);
        $this->assertEquals('192.168.1.10', $record->ip_address);
        $this->assertNotNull($record->completed_at);
    }

    public function test_4_deployment_service_rejects_unauthorized_arbitrary_shell_actions(): void
    {
        /** @var DeploymentService $service */
        $service = app(DeploymentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unauthorized deployment action 'rm -rf /'");

        $service->executeAction('rm -rf /');
    }

    public function test_5_security_headers_middleware_sets_expected_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_6_production_error_handling_suppresses_stack_traces_when_debug_disabled(): void
    {
        config(['app.debug' => false]);

        // Request an invalid non-existent endpoint or trigger exception
        $response = $this->getJson('/api/v1/non_existent_route_9999');

        $response->assertStatus(404);
        $content = $response->getContent();

        $this->assertStringNotContainsString('trace', $content);
        $this->assertStringNotContainsString('file', $content);
        $this->assertStringNotContainsString('line', $content);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate');
    }

    public function test_audit_trails_defaults_to_current_month(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        // Log in current month
        AuditTrail::create([
            'user_id' => $user->id,
            'module' => 'Project',
            'action' => 'Create',
            'record_id' => 1,
            'description' => 'Current month log',
            'created_at' => now(),
        ]);

        // Log 3 months ago
        $old = AuditTrail::create([
            'user_id' => $user->id,
            'module' => 'Project',
            'action' => 'Create',
            'record_id' => 2,
            'description' => 'Old log',
        ]);
        $old->created_at = now()->subMonths(3);
        $old->save();

        $response = $this->actingAs($user)->getJson('/api/audit-trails');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Current month log', $response->json('data.0.description'));
        $this->assertNotNull($response->json('filter_period.from'));
        $this->assertNotNull($response->json('filter_period.to'));
    }

    public function test_custom_range_caps_at_31_days(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($user)->getJson('/api/audit-trails?date_from=2026-01-01&date_to=2026-06-01');

        $response->assertStatus(200);
        $from = $response->json('filter_period.from');
        $to = $response->json('filter_period.to');

        $this->assertEquals('2026-01-01', $from);
        $this->assertEquals('2026-02-01', $to); // Capped to 31 days
    }

    public function test_all_periods_search_allows_cross_month_lookup(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        // Old log with specific finding code
        $legacy = AuditTrail::create([
            'user_id' => $user->id,
            'module' => 'finding',
            'action' => 'create',
            'record_id' => 99,
            'description' => 'Created finding FND-LEGACY-001',
        ]);
        $legacy->created_at = now()->subMonths(5);
        $legacy->save();

        // Without all_periods: should not find it (outside current month)
        $res1 = $this->actingAs($user)->getJson('/api/audit-trails?search=FND-LEGACY-001');
        $res1->assertStatus(200);
        $this->assertCount(0, $res1->json('data'));

        // With all_periods=1: finds it!
        $res2 = $this->actingAs($user)->getJson('/api/audit-trails?search=FND-LEGACY-001&all_periods=1');
        $res2->assertStatus(200);
        $this->assertCount(1, $res2->json('data'));
        $this->assertTrue($res2->json('filter_period.is_all_periods'));
    }
}

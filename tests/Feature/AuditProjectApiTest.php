<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\AuditProject;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_audit_project_with_generated_code(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $company = Company::create([
            'code' => 'ACM',
            'name' => 'Acme Corporation',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'company_id' => $company->id,
                'project_name' => 'Annual Cyber & ITGC Audit',
                'release_date' => '2026-03-01',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Project created successfully',
            ]);

        $year = now()->year;
        $this->assertDatabaseHas('audit_projects', [
            'company_id' => $company->id,
            'project_name' => 'ACM - Annual Cyber & ITGC Audit',
            'project_code' => "AUD-ACM-{$year}-001",
            'status' => 'open',
        ]);
    }

    public function test_create_project_validation_fails_when_fields_missing(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id', 'project_name']);
    }

    public function test_user_can_list_paginated_projects(): void
    {
        $user = User::factory()->create(['role' => 'auditor']);
        $company = Company::create([
            'code' => 'TBA',
            'name' => 'Tech Branch Asia',
        ]);

        AuditProject::create([
            'project_code' => 'AUD-TBA-2026-001',
            'company_id' => $company->id,
            'project_name' => 'Q1 Financial Review',
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_code',
                        'project_name',
                        'company',
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_assigning_lead_auditor_sends_notification_to_lead(): void
    {
        $creator = User::factory()->create(['name' => 'Fahmi Auditor', 'role' => 'auditor']);
        $lead = User::factory()->create(['name' => 'Aulia Lead', 'role' => 'auditor']);
        $company = Company::create([
            'code' => 'ACM',
            'name' => 'Acme Corporation',
        ]);

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/projects', [
                'company_id' => $company->id,
                'project_name' => 'Annual Supply Chain Review',
                'release_date' => '2026-03-01',
                'created_by' => $lead->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $lead->id,
            'type' => 'project_lead_assigned',
        ]);

        $notif = \App\Models\Notification::where('user_id', $lead->id)->first();
        $this->assertStringContainsString('Fahmi Auditor', $notif->message);
        $this->assertStringContainsString('Annual Supply Chain Review', $notif->message);
    }

    public function test_create_project_avoids_duplicate_code_when_existing_projects_and_custom_suffixes_exist(): void
    {
        $creator = User::factory()->create(['role' => 'manager']);
        $company = Company::create([
            'code' => 'FFS',
            'name' => 'FKS Food Sejahtera',
        ]);

        $year = now()->year;

        // Simulate seeded data
        AuditProject::create([
            'project_code' => "AUD-FFS-{$year}-001",
            'company_id' => $company->id,
            'project_name' => 'FFS - Existing Project 1',
            'status' => 'open',
            'created_by' => $creator->id,
        ]);

        AuditProject::create([
            'project_code' => "AUD-FFS-{$year}-002",
            'company_id' => $company->id,
            'project_name' => 'FFS - Existing Project 2',
            'status' => 'open',
            'created_by' => $creator->id,
        ]);

        // Non-numeric suffix like Q4
        AuditProject::create([
            'project_code' => "AUD-FFS-2025-Q4",
            'company_id' => $company->id,
            'project_name' => 'FFS - Q4 Project',
            'status' => 'closed',
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/projects', [
                'company_id' => $company->id,
                'project_name' => 'Percobaan Saja Ini',
                'release_date' => '2026-10-27',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_projects', [
            'company_id' => $company->id,
            'project_name' => 'FFS - Percobaan Saja Ini',
            'project_code' => "AUD-FFS-{$year}-003",
        ]);
    }
}

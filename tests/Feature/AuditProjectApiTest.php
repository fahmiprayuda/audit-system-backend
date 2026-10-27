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
}

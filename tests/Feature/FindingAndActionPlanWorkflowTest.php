<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use App\Models\AuditProject;
use App\Models\Finding;
use App\Models\FindingDepartment;
use App\Models\ActionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FindingAndActionPlanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private User $auditee;
    private Company $company;
    private Department $dept;
    private AuditProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'code' => 'CORP',
            'name' => 'Corporate Indonesia',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'IT',
            'name' => 'IT Department',
        ]);

        $this->auditor = User::factory()->create([
            'role' => 'auditor',
            'department_id' => null,
        ]);

        $this->auditee = User::factory()->create([
            'role' => 'auditee',
            'department_id' => $this->dept->id,
        ]);

        $this->project = AuditProject::create([
            'project_code' => 'AUD-CORP-2026-001',
            'company_id' => $this->company->id,
            'project_name' => 'CORP - Annual IT Audit',
            'status' => 'open',
            'created_by' => $this->auditor->id,
        ]);
    }

    public function test_auditor_can_create_finding_with_action_plans(): void
    {
        $response = $this->actingAs($this->auditor, 'sanctum')
            ->postJson('/api/findings', [
                'audit_project_id' => $this->project->id,
                'title' => 'Server Public SSH Exposed Without MFA',
                'description' => 'Port 22 open directly to internet.',
                'risk_rating' => 'Extreme',
                'departments' => [$this->dept->id],
                'action_plans' => [
                    [
                        'department_id' => $this->dept->id,
                        'corrective_action' => 'Setup Bastion Host and configure Cloudflare Zero Trust MFA',
                        'root_cause' => 'Legacy migration left port 22 open',
                        'due_date' => now()->addMonths(2)->format('Y-m-d'),
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Finding + Action Plans created successfully',
            ]);

        $year = now()->year;
        $this->assertDatabaseHas('findings', [
            'audit_project_id' => $this->project->id,
            'title' => 'Server Public SSH Exposed Without MFA',
            'risk_rating' => 'Extreme',
            'risk_category' => 'Significant',
            'finding_code' => "FND-CORP-{$year}-001",
        ]);

        $this->assertDatabaseHas('action_plans', [
            'corrective_action' => 'Setup Bastion Host and configure Cloudflare Zero Trust MFA',
            'status' => 'need_further_review',
        ]);
    }

    public function test_auditor_can_create_finding_with_default_risk_rating_when_omitted(): void
    {
        $response = $this->actingAs($this->auditor, 'sanctum')
            ->postJson('/api/findings', [
                'audit_project_id' => $this->project->id,
                'title' => 'Lack of Dual Approval in Wire Transfer',
                'description' => 'Transfer above $10k only requires 1 approval.',
                'departments' => [$this->dept->id],
                'action_plans' => [
                    [
                        'department_id' => $this->dept->id,
                        'corrective_action' => 'Enforce multi-sig authorization in core banking',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('findings', [
            'audit_project_id' => $this->project->id,
            'title' => 'Lack of Dual Approval in Wire Transfer',
            'risk_rating' => 'Moderate',
            'risk_category' => 'Moderate',
        ]);
    }

    public function test_auditee_can_submit_action_plan_comment_and_trigger_submitted_flag(): void
    {
        $finding = Finding::create([
            'audit_project_id' => $this->project->id,
            'finding_code' => 'FND-CORP-2026-001',
            'title' => 'Sample Finding',
            'risk_rating' => 'Major',
            'risk_category' => 'Significant',
            'created_by' => $this->auditor->id,
            'status' => 'open',
        ]);

        $fd = FindingDepartment::create([
            'finding_id' => $finding->id,
            'department_id' => $this->dept->id,
            'status' => 'open',
        ]);

        $ap = ActionPlan::create([
            'finding_department_id' => $fd->id,
            'corrective_action' => 'Implement password rotation',
            'status' => 'open',
            'flags' => [],
        ]);

        $response = $this->actingAs($this->auditee, 'sanctum')
            ->postJson("/api/action-plans/{$ap->id}/comment", [
                'message' => 'Evidence uploaded. Policy has been pushed via Active Directory GPO.',
                'workflow_action' => 'submitted',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Comment added',
            ]);

        $ap->refresh();
        $this->assertTrue($ap->hasFlag('submitted'));
    }

    public function test_auditor_can_close_action_plan(): void
    {
        $finding = Finding::create([
            'audit_project_id' => $this->project->id,
            'finding_code' => 'FND-CORP-2026-001',
            'title' => 'Sample Finding to Close',
            'risk_rating' => 'Moderate',
            'risk_category' => 'Moderate',
            'created_by' => $this->auditor->id,
            'status' => 'open',
        ]);

        $fd = FindingDepartment::create([
            'finding_id' => $finding->id,
            'department_id' => $this->dept->id,
            'status' => 'open',
        ]);

        $ap = ActionPlan::create([
            'finding_department_id' => $fd->id,
            'corrective_action' => 'Reconciled all balances',
            'status' => 'open',
            'flags' => ['submitted'],
        ]);

        $response = $this->actingAs($this->auditor, 'sanctum')
            ->postJson("/api/action-plans/{$ap->id}/close");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Closed',
            ]);

        $ap->refresh();
        $this->assertEquals('closed', $ap->status);
        $this->assertNotNull($ap->closed_at);
        $this->assertEquals($this->auditor->id, $ap->closed_by);
    }
}

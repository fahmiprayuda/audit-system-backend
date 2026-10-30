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
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CheckOverdueActionPlansTest extends TestCase
{
    use RefreshDatabase;

    private User $auditee;
    private Department $dept;
    private FindingDepartment $findingDept;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate');

        $company = Company::create([
            'code' => 'CORP',
            'name' => 'Corporate Indonesia',
        ]);

        $this->dept = Department::create([
            'company_id' => $company->id,
            'code' => 'IT',
            'name' => 'IT Department',
        ]);

        $auditor = User::factory()->create([
            'role' => 'auditor',
            'department_id' => null,
        ]);

        $this->auditee = User::factory()->create([
            'role' => 'auditee',
            'department_id' => $this->dept->id,
        ]);

        $project = AuditProject::create([
            'project_code' => 'AUD-CORP-2026-001',
            'company_id' => $company->id,
            'project_name' => 'CORP - Annual IT Audit',
            'status' => 'open',
            'created_by' => $auditor->id,
        ]);

        $finding = Finding::create([
            'audit_project_id' => $project->id,
            'finding_code' => 'CORP-IT-2026-001',
            'title' => 'Missing server backups',
            'risk_rating' => 'Extreme',
            'risk_category' => 'Significant',
            'status' => 'open',
            'created_by' => $auditor->id,
        ]);

        $this->findingDept = FindingDepartment::create([
            'finding_id' => $finding->id,
            'department_id' => $this->dept->id,
        ]);
    }

    public function test_command_detects_overdue_action_plans_and_creates_notifications(): void
    {
        $overduePlan = ActionPlan::create([
            'finding_department_id' => $this->findingDept->id,
            'root_cause' => 'No automated backup script',
            'corrective_action' => 'Deploy daily cron backup',
            'due_date' => Carbon::yesterday()->toDateString(),
            'status' => 'open',
            'is_overdue' => false,
        ]);

        $this->artisan('audit:check-overdue')
            ->expectsOutputToContain('Starting automated audit action plan overdue & reminder check...')
            ->assertExitCode(0);

        // Plan should now be flagged overdue
        $this->assertTrue($overduePlan->fresh()->is_overdue);

        // Auditee should receive notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->auditee->id,
            'type' => 'action_plan_overdue',
        ]);
    }

    public function test_command_detects_due_soon_action_plans_and_creates_reminders(): void
    {
        $dueSoonPlan = ActionPlan::create([
            'finding_department_id' => $this->findingDept->id,
            'root_cause' => 'Manual access logs',
            'corrective_action' => 'Setup SSO',
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
            'status' => 'open',
            'is_overdue' => false,
        ]);

        $this->artisan('audit:check-overdue')
            ->assertExitCode(0);

        // Plan should not be overdue
        $this->assertFalse($dueSoonPlan->fresh()->is_overdue);

        // Auditee should receive due soon reminder notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->auditee->id,
            'type' => 'action_plan_due_soon',
        ]);
    }

    public function test_command_ignores_closed_action_plans(): void
    {
        $closedPlan = ActionPlan::create([
            'finding_department_id' => $this->findingDept->id,
            'root_cause' => 'Old bug',
            'corrective_action' => 'Already fixed and verified',
            'due_date' => Carbon::yesterday()->toDateString(),
            'status' => 'closed',
            'is_overdue' => false,
        ]);

        $this->artisan('audit:check-overdue')
            ->assertExitCode(0);

        $this->assertFalse($closedPlan->fresh()->is_overdue);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->auditee->id,
            'type' => 'action_plan_overdue',
        ]);
    }
}

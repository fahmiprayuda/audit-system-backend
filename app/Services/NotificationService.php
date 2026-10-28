<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ActionPlan;
use App\Models\User;

class NotificationService
{
    /*
    |--------------------------------------------------------------------------
    | Base
    |--------------------------------------------------------------------------
    */

    public static function create(
        $userId,
        $type,
        $title,
        $message,
        $url = null
    ) {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Receivers
    |--------------------------------------------------------------------------
    */

    protected static function auditees(ActionPlan $ap)
    {
        return User::where(
            'role',
            'auditee'
        )
        ->where(
            'department_id',
            $ap->findingDepartment->department_id
        )
        ->pluck('id');
    }

    protected static function auditors()
    {
        return User::whereIn(
            'role',
            ['auditor', 'manager']
        )->pluck('id');
    }

    public static function newActionPlan(ActionPlan $ap)
    {
        $finding = $ap->findingDepartment?->finding;
        $findingTitle = $finding ? "\"{$finding->title}\"" : "temuan audit";

        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'action_plan_created',
                'Action Plan Baru Ditugaskan',
                "Action Plan baru telah ditugaskan ke departemen Anda untuk {$findingTitle}.",
                $ap->detail_url
            );
        }
    }

    public static function submitted(ActionPlan $ap)
    {
        foreach (self::auditors() as $userId) {

            self::create(
                $userId,
                'submitted',
                'Action Plan Submitted',
                'An Action Plan has been submitted for review.',
                $ap->detail_url
            );
        }
    }

    public static function revisionRequired(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'revision_required',
                'Revision Required',
                'Additional revision is required.',
                $ap->detail_url
            );
        }
    }

    public static function siteValidation(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'site_validation',
                'Site Validation Required',
                'An on-site validation has been scheduled.',
                $ap->detail_url
            );
        }
    }

    public static function actionPlanClosed(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'closed',
                'Action Plan Closed',
                'Your Action Plan has been closed.',
                $ap->detail_url
            );
        }
    }

    public static function comment(ActionPlan $ap, User $sender)
    {
        if ($sender->role === 'auditee') {

            $receivers = self::auditors();

        } else {

            $receivers = self::auditees($ap);
        }

        foreach ($receivers as $userId) {

            if ($userId == $sender->id) {
                continue;
            }

            self::create(
                $userId,
                'comment',
                'New Comment',
                "{$sender->name} sent a new message.",
                $ap->detail_url
            );
        }
    }

    public static function projectLeadAssigned(\App\Models\AuditProject $project, User $lead, ?User $assigner = null)
    {
        $assignerName = $assigner ? $assigner->name : 'Sistem';

        $message = ($assigner && $assigner->id !== $lead->id)
            ? "Halo {$lead->name}, Anda telah ditugaskan sebagai Lead Auditor untuk project \"{$project->project_name}\" ({$project->project_code}) oleh {$assignerName}."
            : "Project \"{$project->project_name}\" ({$project->project_code}) berhasil dibuat dengan Anda sebagai Lead Auditor.";

        self::create(
            $lead->id,
            'project_lead_assigned',
            'Penugasan Lead Auditor',
            $message,
            "/projects/{$project->id}"
        );
    }

    public static function findingAssigned(\App\Models\Finding $finding, int $departmentId, ?User $auditor = null, int $actionPlansCount = 0)
    {
        $department = \App\Models\Department::find($departmentId);
        $deptName = $department ? $department->name : 'Departemen';
        $auditorName = $auditor ? $auditor->name : 'Auditor';
        $projectName = $finding->project ? $finding->project->project_name : 'Audit Project';

        $auditees = User::where('role', 'auditee')
            ->where('department_id', $departmentId)
            ->get();

        foreach ($auditees as $auditee) {
            $message = $actionPlansCount > 0
                ? "Temuan baru \"{$finding->title}\" ({$finding->finding_code}) beserta {$actionPlansCount} Action Plan telah ditugaskan ke departemen {$deptName} oleh {$auditorName}."
                : "Temuan baru \"{$finding->title}\" ({$finding->finding_code}) telah ditugaskan ke departemen {$deptName} oleh {$auditorName}.";

            self::create(
                $auditee->id,
                'finding_created',
                'Temuan Audit Baru Ditugaskan',
                $message,
                "/findings/{$finding->id}"
            );
        }
    }
}
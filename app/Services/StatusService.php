<?php

namespace App\Services;

use App\Models\FindingDepartment;
use App\Models\Finding;
use App\Models\AuditProject;

class StatusService
{
    public static function sync($findingDepartmentId)
    {
        $fd = FindingDepartment::with('actionPlans')
            ->find($findingDepartmentId);

        if (!$fd) {
            return;
        }

        // ===================================
        // FINDING STATUS
        // ===================================

        self::syncFinding($fd->finding_id);
    }

    private static function syncFinding($findingId)
    {
        $finding = Finding::with(
            'findingDepartments.actionPlans'
        )->find($findingId);

        if (!$finding) {
            return;
        }

        // Ambil semua AP dari semua department
        $actionPlans = $finding->findingDepartments
            ->flatMap->actionPlans;

        // Belum ada AP
        if ($actionPlans->count() === 0) {

            $finding->status = 'open';

        } else {

            // Semua AP approved
            $allApproved = $actionPlans
                ->every(fn($ap) => $ap->status === 'approved');

            $finding->status = $allApproved
                ? 'closed'
                : 'need_further_review';
        }

        $finding->save();

        self::syncProject(
            $finding->audit_project_id
        );
    }

    private static function syncProject($projectId)
    {
        $project = AuditProject::with('findings')
            ->find($projectId);

        if (!$project) {
            return;
        }

        // Belum ada finding
        if ($project->findings->count() === 0) {

            $project->status = 'open';

        } else {

            $allClosed = $project->findings
                ->every(fn($f) => $f->status === 'closed');

            $project->status = $allClosed
                ? 'closed'
                : 'in_progress';
        }

        $project->save();
    }
}
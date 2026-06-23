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

        $actionPlans =
            $finding->findingDepartments
                ->flatMap->actionPlans;

        if ($actionPlans->isEmpty()) {

            $finding->status =
                'need_further_review';

        } else {

            foreach ($actionPlans as $ap) {

                if (
                    $ap->status !== 'closed'
                    &&
                    $ap->due_date
                    &&
                    $ap->due_date->isPast()
                ) {

                    $ap->status = 'open';

                    $ap->addFlag('overdue');
                }
            }

            $allClosed = $actionPlans->every(
                fn($ap) => $ap->status === 'closed'
            );

            $hasOpen = $actionPlans->contains(
                fn($ap) => $ap->status === 'open'
            );

            if ($allClosed) {

                $finding->status = 'closed';

            } elseif ($hasOpen) {

                $finding->status = 'open';

            } else {

                $finding->status =
                    'need_further_review';
            }
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

            $project->status = 'need_further_review';

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
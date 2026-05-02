<?php

namespace App\Services;

use App\Models\FindingDepartment;
use App\Models\Finding;
use App\Models\AuditProject;

class StatusService
{
    public static function sync($findingDepartmentId)
    {
        $fd = FindingDepartment::with('actionPlans')->find($findingDepartmentId);

        if (!$fd) return;

        $statuses = $fd->actionPlans->pluck('status');

        // ================= FD =================
        if ($statuses->contains('need_revision')) {
            $fd->status = 'need_revision';
        } elseif ($statuses->every(fn($s) => $s === 'verified')) {
            $fd->status = 'closed';
        } elseif ($statuses->contains('submitted') || $statuses->contains('approved')) {
            $fd->status = 'in_progress';
        } else {
            $fd->status = 'open';
        }

        $fd->save();

        self::syncFinding($fd->finding_id);
    }

    private static function syncFinding($findingId)
    {
        $finding = Finding::with('findingDepartments')->find($findingId);

        if (!$finding) return;

        $statuses = $finding->findingDepartments->pluck('status');

        if ($statuses->contains('need_revision')) {
            $finding->status = 'need_revision';
        } elseif ($statuses->every(fn($s) => $s === 'closed')) {
            $finding->status = 'closed';
        } elseif ($statuses->contains('in_progress')) {
            $finding->status = 'in_progress';
        } else {
            $finding->status = 'open';
        }

        $finding->save();

        self::syncProject($finding->audit_project_id);
    }

    private static function syncProject($projectId)
    {
        $project = AuditProject::with('findings')->find($projectId);

        if (!$project) return;

        $statuses = $project->findings->pluck('status');

        if ($statuses->contains('need_revision')) {
            $project->status = 'need_revision';
        } elseif ($statuses->every(fn($s) => $s === 'closed')) {
            $project->status = 'closed';
        } elseif ($statuses->contains('in_progress')) {
            $project->status = 'in_progress';
        } else {
            $project->status = 'open';
        }

        $project->save();
    }
}
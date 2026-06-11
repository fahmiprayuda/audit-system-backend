<?php

namespace App\Services;

use App\Models\AuditTrail;

class AuditTrailService
{
    public static function log(
        $module,
        $action,
        $recordId,
        $description,
        $oldValues = null,
        $newValues = null
    ) {
        AuditTrail::create([
            'user_id' => auth()->id(),
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
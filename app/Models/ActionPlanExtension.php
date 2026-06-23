<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlanExtension extends Model
{
    protected $fillable = [
        'action_plan_id',
        'old_due_date',
        'new_due_date',
        'status_after_extension',
        'reason',
        'extended_by'
    ];

    protected $casts = [
        'old_due_date' => 'date',
        'new_due_date' => 'date',
    ];

    public function actionPlan()
    {
        return $this->belongsTo(
            ActionPlan::class
        );
    }

    public function extender()
    {
        return $this->belongsTo(
            User::class,
            'extended_by'
        );
    }
}

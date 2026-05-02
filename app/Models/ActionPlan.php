<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    protected $fillable = [
        'finding_department_id',
        'root_cause',
        'corrective_action',
        'target_date',
        'status',
    ];

    public function findingDepartment()
    {
        return $this->belongsTo(FindingDepartment::class, 'finding_department_id');
    }

    public function evidences() // 🔥 rename
    {
        return $this->hasMany(Evidence::class);
    }

    public function verifications()
    {
        return $this->hasMany(Verification::class);
    }

    public function canTransitionTo($newStatus)
    {
        $flow = [
            'draft' => ['submitted'],
            'need_revision' => ['submitted'], // 🔥 ini penting
            'submitted' => ['approved'],
            'approved' => ['in_progress'],
            'in_progress' => ['done'],
            'done' => ['verified'],
        ];

        return in_array($newStatus, $flow[$this->status] ?? []);
    }

    public function comments()
    {
        return $this->hasMany(ActionPlanComment::class);
    }
}
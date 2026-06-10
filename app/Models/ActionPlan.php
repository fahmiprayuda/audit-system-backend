<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    protected $casts = [
        'due_date' => 'date',
    ];

    protected $fillable = [
        'finding_department_id',
        'root_cause',
        'corrective_action',
        'due_date',
        'status',
    ];

    public function findingDepartment()
    {
        return $this->belongsTo(FindingDepartment::class);
    }

    public function verifications()
    {
        return $this->hasMany(Verification::class);
    }

    public function canTransitionTo($newStatus)
    {
        $flow = [

            'draft' => [
                'submitted'
            ],

            'submitted' => [
                'approved',
                'need_revision'
            ],

            'need_revision' => [
                'submitted'
            ],

            'approved' => []

        ];

        return in_array(
            $newStatus,
            $flow[$this->status] ?? []
        );
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date
            && $this->due_date < now()->toDateString()
            && $this->status !== 'approved';
    }

    public function comments()
    {
        return $this->hasMany(ActionPlanComment::class);
    }
}
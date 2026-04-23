<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingDepartment extends Model
{
    protected $fillable = [
        'finding_id',
        'department_id',
    ];

    protected $appends = ['status'];

    public function getStatusAttribute()
    {
        $actions = $this->actionPlans;

        if ($actions->isEmpty()) return 'open';

        if ($actions->contains(fn($a) => $a->status === 'in_progress')) {
            return 'in_progress';
        }

        if ($actions->every(fn($a) => $a->status === 'verified')) {
            return 'closed';
        }

        if ($actions->every(fn($a) => in_array($a->status, ['done', 'verified']))) {
            return 'pending_verify';
        }

        return 'open';
    }

    public function finding()
    {
        return $this->belongsTo(Finding::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class, 'finding_department_id');
    }

}


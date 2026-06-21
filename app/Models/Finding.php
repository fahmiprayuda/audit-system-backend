<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finding extends Model
{
    protected $fillable = [
        'audit_project_id',
        'finding_code',
        'title',
        'description',
        'risk_rating',
        'risk_category',
        'created_by',
        'status'
    ];


    public function project()
    {
    return $this->belongsTo(AuditProject::class,'audit_project_id');
    }

    public function actionPlans()
    {
        return $this->hasManyThrough(
            ActionPlan::class,
            FindingDepartment::class,
            'finding_id',
            'finding_department_id'
        );
    }

    public function syncStatus()
    {
        $actionPlans = $this->actionPlans();

        if ($actionPlans->count() === 0) {

            $this->update([
                'status' => 'open'
            ]);

        } else {

            $allClosed = $actionPlans
                ->where('status', '!=', 'closed')
                ->doesntExist();

            $this->update([
                'status' => $allClosed
                    ? 'closed'
                    : 'need_further_review'
            ]);
        }

        // sync project juga
        $this->project?->syncStatus();
    }

    public function findingDepartments()
    {
        return $this->hasMany(FindingDepartment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDueDateAttribute()
    {
        return $this->actionPlans()->max('due_date');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingDepartment extends Model
{
    protected $fillable = [
        'finding_id',
        'department_id',
    ];


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


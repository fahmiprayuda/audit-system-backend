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
    'due_date',
    'status',
    'created_by'
    ];

    public function project()
    {
    return $this->belongsTo(AuditProject::class,'audit_project_id');
    }

    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            'finding_departments'
        );
    }

    public function findingDepartments()
    {
        return $this->hasMany(FindingDepartment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
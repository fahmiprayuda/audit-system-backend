<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditProject extends Model
{

    protected $fillable = [
        'project_code',
        'company_id',
        'project_name',
        'start_date',
        'end_date',
        'status',
        'created_by'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class,'audit_project_id');
    }

}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function findings()
    {
        return $this->belongsToMany(
            Finding::class,
            'finding_departments'
        );
    }

    public function findingDepartments()
    {
        return $this->hasMany(
            FindingDepartment::class
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
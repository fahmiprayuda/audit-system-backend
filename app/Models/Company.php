<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{

    protected $fillable = [
        'code',
        'name'
    ];

    public function auditProjects()
    {
        return $this->hasMany(AuditProject::class);
    }

}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code'
    ];

    public function findings()
    {
        return $this->belongsToMany(
            Finding::class,
            'finding_departments'
        );
    }
}
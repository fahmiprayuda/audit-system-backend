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
        'created_by'
    ];

    protected $appends = ['status'];

    public function getStatusAttribute()
    {
        $findings = $this->findings;

        if ($findings->isEmpty()) return 'open';

        if ($findings->every(fn($f) => $f->status === 'closed')) {
            return 'closed';
        }

        if ($findings->contains(fn($f) => $f->status === 'in_progress')) {
            return 'ongoing';
        }

        if ($findings->contains(fn($f) => $f->status === 'pending_verify')) {
            return 'review';
        }

        return 'open';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class,'audit_project_id');
    }

}
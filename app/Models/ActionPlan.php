<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    protected $casts = [
        'due_date' => 'date',
        'flags' => 'array',
    ];

    protected $fillable = [
        'finding_department_id',
        'root_cause',
        'corrective_action',
        'due_date',
        'status',
        'flags',
    ];

    public function findingDepartment()
    {
        return $this->belongsTo(FindingDepartment::class);
    }

    public function verifications()
    {
        return $this->hasMany(Verification::class);
    }

    public function extensions()
    {
        return $this->hasMany(ActionPlanExtension::class);
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date
            && $this->due_date < now()->toDateString()
            && $this->status !== 'closed';
    }

    public function comments()
    {
        return $this->hasMany(ActionPlanComment::class);
    }

    public function isOverdue()
    {
        return
            $this->status !== 'closed'
            &&
            $this->due_date?->isPast();
    }

    public function syncOverdue()
    {
        if ($this->status === 'closed') {
            return;
        }

        if (!$this->isOverdue()) {

            $this->removeFlag('overdue');

            return;
        }

        $this->addFlag('overdue');

        $hasResponse =
            $this->hasFlag('submitted')
            ||
            $this->hasFlag('revision_required')
            ||
            $this->hasFlag('on_site_validation');

        if (!$hasResponse) {

            $this->status = 'open';

            $this->save();
        }
    }

    public function hasFlag($flag)
    {
        return in_array(
            $flag,
            $this->flags ?? []
        );
    }
    public function isSubmitted()
    {
        return $this->hasFlag('submitted');
    }

    public function needsRevision()
    {
        return $this->hasFlag('revision_required');
    }

    public function addFlag($flag)
    {
        $flags = collect($this->flags ?? [])
            ->push($flag)
            ->unique()
            ->values()
            ->toArray();

        $this->flags = $flags;

        $this->save();
    }

    public function removeFlag($flag)
    {
        $flags = collect(
            $this->flags ?? []
        )
        ->reject(fn($f) => $f === $flag)
        ->values()
        ->toArray();

        $this->update([
            'flags' => $flags
        ]);
    }
}
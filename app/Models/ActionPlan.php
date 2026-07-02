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

    public function getIsOverdueAttribute() : bool
    {
        return $this->isOverdue();
    }

    public function comments()
    {
        return $this->hasMany(ActionPlanComment::class);
    }

    public function isOverdue() : bool
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

    public function getPrimaryFlagAttribute(): ?string
    {
        foreach ([
            'revision_required',
            'submitted',
            'on_site_validation',
        ] as $flag) {
            if ($this->hasFlag($flag)) {
                return $flag;
            }
        }

        return null;
    }

    public function getQueueAttribute(): string
    {
        if ($this->status === 'closed') {
            return 'closed';
        }

        return match ($this->primary_flag) {

            'revision_required' => 'revision',

            'submitted' => 'waiting',

            'on_site_validation' => 'site',

            default => $this->hasFlag('overdue')
                ? 'overdue'
                : 'new',
        };
    }

    public function replacePrimaryFlag(string $flag)
    {
    $allowed = [
        'submitted',
        'revision_required',
        'on_site_validation',
        ];
    if (!in_array($flag, $allowed)) {
        throw new \InvalidArgumentException(
            "Invalid primary flag [$flag]"
        );
    }

    $flags = collect($this->flags ?? [])

            ->reject(fn ($f) => in_array($f, [
                'submitted',
                'revision_required',
                'on_site_validation',
            ]))

            ->values()

            ->toArray();

        $flags[] = $flag;

        $this->update([
            'flags' => array_values(array_unique($flags))
        ]);
    }

    public function getDetailUrlAttribute()
    {
        $findingId = $this->findingDepartment->finding_id;

        return "/findings/{$findingId}?fd={$this->finding_department_id}&ap={$this->id}";
    }
}
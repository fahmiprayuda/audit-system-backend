<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    protected $fillable = [
        'action_plan_id',
        'verified_by',
        'verification_note',
        'status',
        'verified_at'
    ];

    public function actionPlan()
    {
        return $this->belongsTo(ActionPlan::class);
    }
}
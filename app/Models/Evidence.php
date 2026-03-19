<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evidence extends Model
{
    protected $table = 'evidences';   // ← penting

    protected $fillable = [
        'action_plan_id',
        'file_path',
        'uploaded_by'
    ];

    public function actionPlan()
    {
        return $this->belongsTo(ActionPlan::class);
    }
}
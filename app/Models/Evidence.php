<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evidence extends Model
{
    protected $table = 'evidences';   // ← penting

    protected $fillable = [
    'action_plan_id',
    'file_path',
    'file_name',
    'uploaded_by',
];

    public function actionPlan()
    {
        return $this->belongsTo(ActionPlan::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

}
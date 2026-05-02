<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlanComment extends Model
{
    protected $fillable = [
        'action_plan_id',
        'role',
        'message'
    ];

    public function actionPlan()
    {
        return $this->belongsTo(ActionPlan::class);
    }
}
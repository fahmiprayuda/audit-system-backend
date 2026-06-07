<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlanComment extends Model
{
    protected $fillable = [
        'action_plan_id',
        'role',
        'message',
        'created_by'
    ];

    public function actionPlan()
    {
        return $this->belongsTo(ActionPlan::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(
            ActionPlanCommentAttachment::class
        );
    }

}
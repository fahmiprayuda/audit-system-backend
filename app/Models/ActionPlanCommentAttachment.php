<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionPlanCommentAttachment extends Model
{
    protected $fillable = [
        'action_plan_comment_id',
        'file_name',
        'file_path',
        'uploaded_by'
    ];

    public function comment()
    {
        return $this->belongsTo(
            ActionPlanComment::class,
            'action_plan_comment_id'
        );
    }

    public function uploader()
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}
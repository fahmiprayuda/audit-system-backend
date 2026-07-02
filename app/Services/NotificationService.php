<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ActionPlan;
use App\Models\User;

class NotificationService
{
    /*
    |--------------------------------------------------------------------------
    | Base
    |--------------------------------------------------------------------------
    */

    public static function create(
        $userId,
        $type,
        $title,
        $message,
        $url = null
    ) {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Receivers
    |--------------------------------------------------------------------------
    */

    protected static function auditees(ActionPlan $ap)
    {
        return User::where(
            'role',
            'auditee'
        )
        ->where(
            'department_id',
            $ap->findingDepartment->department_id
        )
        ->pluck('id');
    }

    protected static function auditors()
    {
        return User::whereIn(
            'role',
            ['auditor', 'manager']
        )->pluck('id');
    }

    public static function newActionPlan(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'action_plan_created',
                'New Action Plan',
                'A new Action Plan has been assigned to your department.',
                $ap->detail_url
            );
        }
    }

    public static function submitted(ActionPlan $ap)
    {
        foreach (self::auditors() as $userId) {

            self::create(
                $userId,
                'submitted',
                'Action Plan Submitted',
                'An Action Plan has been submitted for review.',
                $ap->detail_url
            );
        }
    }

    public static function revisionRequired(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'revision_required',
                'Revision Required',
                'Additional revision is required.',
                $ap->detail_url
            );
        }
    }

    public static function siteValidation(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'site_validation',
                'Site Validation Required',
                'An on-site validation has been scheduled.',
                $ap->detail_url
            );
        }
    }

    public static function actionPlanClosed(ActionPlan $ap)
    {
        foreach (self::auditees($ap) as $userId) {

            self::create(
                $userId,
                'closed',
                'Action Plan Closed',
                'Your Action Plan has been closed.',
                $ap->detail_url
            );
        }
    }

    public static function comment(ActionPlan $ap, User $sender)
    {
        if ($sender->role === 'auditee') {

            $receivers = self::auditors();

        } else {

            $receivers = self::auditees($ap);
        }

        foreach ($receivers as $userId) {

            if ($userId == $sender->id) {
                continue;
            }

            self::create(
                $userId,
                'comment',
                'New Comment',
                "{$sender->name} sent a new message.",
                $ap->detail_url
            );
        }
    }


}
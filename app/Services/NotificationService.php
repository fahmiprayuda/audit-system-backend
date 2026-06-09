<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
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
}

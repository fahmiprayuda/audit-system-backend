<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(30)
            ->get([
                'id',
                'title',
                'message',
                'url',
                'read_at',
                'created_at'
            ]);
    }

    public function unreadCount()
    {
        return [
            'count' => auth()
                ->user()
                ->notifications()
                ->whereNull('read_at')
                ->count()
        ];
    }

    public function markRead($id)
    {
        $notif = Notification::findOrFail($id);

        $notif->update([
            'read_at' => now()
        ]);

        return [
            'message' => 'ok'
        ];
    }
}

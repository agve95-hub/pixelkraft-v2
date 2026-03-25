<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::query()
            ->with('site:id,name,slug')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Notification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'is_read'    => $n->is_read,
                'site'       => $n->site ? ['id' => $n->site->id, 'name' => $n->site->name] : null,
                'data'       => $n->data,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $notifications]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::unread()->update(['is_read' => true]);

        return response()->json(['status' => 'ok']);
    }
}

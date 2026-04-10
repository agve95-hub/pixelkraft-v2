<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = request()->user();
        $visibleSiteIds = Site::query()
            ->visibleTo($user)
            ->pluck('id');

        $notifications = Notification::query()
            ->when(
                ! $user?->isAdmin(),
                fn ($query) => $query->whereIn('site_id', $visibleSiteIds)
            )
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
        $user = request()->user();
        $visibleSiteIds = Site::query()
            ->visibleTo($user)
            ->pluck('id');

        $notification = Notification::query()
            ->whereKey($id)
            ->when(
                ! $user?->isAdmin(),
                fn ($query) => $query->whereIn('site_id', $visibleSiteIds)
            )
            ->firstOrFail();
        $notification->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead(): JsonResponse
    {
        $user = request()->user();
        $visibleSiteIds = Site::query()
            ->visibleTo($user)
            ->pluck('id');

        Notification::query()
            ->unread()
            ->when(
                ! $user?->isAdmin(),
                fn ($query) => $query->whereIn('site_id', $visibleSiteIds)
            )
            ->update(['is_read' => true]);

        return response()->json(['status' => 'ok']);
    }
}

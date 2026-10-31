<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell menu in the top bar.
 *
 * The list is fetched when the bell is opened rather than shared with every
 * response: it sits on every page and most page loads will never show it.
 * Only the unread count is shared, which is one indexed count.
 *
 * Each row carries a translation key and its parameters, so the text is
 * built in the browser in whatever language is active — a notification
 * written in Malay reads in English once the owner switches (PLAN.md 4).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'key' => $notification->data['key'] ?? 'notification.unknown',
                'params' => $notification->data['params'] ?? [],
                'url' => $notification->data['url'] ?? null,
                'tone' => $notification->data['tone'] ?? 'info',
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->whereKey($notification)
            ->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}

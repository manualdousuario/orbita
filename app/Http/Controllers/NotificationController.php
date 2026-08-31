<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Notification listing and read-state endpoints.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) Auth::id();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $unreadCount = Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return view('notifications.index', [
            'unreadCount' => $unreadCount,
            'filter' => $filter,
        ]);
    }

    public function markRead(int $id): RedirectResponse
    {
        Notification::query()
            ->where('id', $id)
            ->where('user_id', (int) Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);

        return redirect()->route('notifications.index');
    }

    public function markAllRead(): RedirectResponse
    {
        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);

        return redirect()->route('notifications.index')
            ->with('success', 'Todas as notificações foram marcadas como lidas.');
    }
}

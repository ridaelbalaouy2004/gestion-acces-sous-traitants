<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareUnreadNotificationCount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $unreadCount = $request->isMethod('GET') || $request->isMethod('HEAD')
            ? ($user instanceof User ? $user->unreadNotifications()->count() : 0)
            : 0;

        View::share('unreadNotificationCount', $unreadCount);

        return $next($request);
    }
}

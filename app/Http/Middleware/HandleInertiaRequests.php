<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $notifications = ['items' => [], 'unread_count' => 0];

        if ($request->user()) {
            $notifications = [
                'items' => $request->user()
                    ->unreadNotifications()
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(function ($n) {
                        return [
                            'id' => $n->id,
                            'kind' => $n->data['kind'] ?? class_basename($n->type),
                            'data' => $n->data,
                            'created_at' => $n->created_at,
                        ];
                    })
                    ->all(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => $notifications,
        ];
    }
}

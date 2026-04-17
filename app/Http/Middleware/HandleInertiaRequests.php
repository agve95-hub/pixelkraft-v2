<?php

namespace App\Http\Middleware;

use App\Support\SiteAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $navSites = [];
        if ($user) {
            $navSites = SiteAccess::query()
                ->withCount([
                    'inboxMessages as unread_inbox_count' => fn ($q) => $q->where('direction', 'inbound')->where('is_read', false),
                    'invoices as unpaid_invoices_count' => fn ($q) => $q->where('status', 'unpaid'),
                    'reminders as overdue_reminders_count' => fn ($q) => $q->whereDate('due_date', '<', now())->where('is_done', false),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'deploy_status', 'maintenance_settings'])
                ->toArray();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
            ],
            'navSites' => $navSites,
            'expandedSiteId' => $request->session()->get('expanded_site_id'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginTrackerController extends Controller
{
    public function index(Request $request)
    {
        $totalLogins = LoginActivity::count();
        $todayLogins = LoginActivity::whereDate('created_at', today())->count();
        $weekLogins = LoginActivity::where('created_at', '>=', now()->startOfWeek())->count();
        $adminAssistedLogins = LoginActivity::whereNotNull('actor_user_id')->count();
        $uniqueUsers = LoginActivity::query()->distinct()->count('user_id');

        $topUsers = User::query()
            ->select('id', 'name', 'email', 'username', 'role')
            ->withCount('loginActivities')
            ->withMax('loginActivities', 'created_at')
            ->get()
            ->filter(fn (User $user) => ($user->login_activities_count ?? 0) > 0)
            ->sortByDesc('login_activities_count')
            ->values()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'login_count' => $user->login_activities_count ?? 0,
                    'last_login_at' => $user->login_activities_max_created_at,
                ];
            });

        $recentActivities = LoginActivity::with([
            'user:id,name,email,username,role',
            'actor:id,name,email,username,role',
        ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (LoginActivity $activity) {
                return [
                    'id' => $activity->id,
                    'user' => [
                        'id' => $activity->user?->id,
                        'name' => $activity->user_name ?: $activity->user?->name,
                        'email' => $activity->user_email ?: $activity->user?->email,
                        'username' => $activity->user_username ?: $activity->user?->username,
                        'role' => $activity->user_role ?: $activity->user?->role,
                    ],
                    'actor' => $activity->actor ? [
                        'id' => $activity->actor->id,
                        'name' => $activity->actor->name,
                        'email' => $activity->actor->email,
                        'username' => $activity->actor->username,
                        'role' => $activity->actor->role,
                    ] : null,
                    'ip_address' => $activity->ip_address,
                    'created_at' => $activity->created_at?->toISOString(),
                ];
            });

        return Inertia::render('LoginTracker/Index', [
            'summary' => [
                'total_logins' => $totalLogins,
                'today_logins' => $todayLogins,
                'week_logins' => $weekLogins,
                'unique_users' => $uniqueUsers,
                'admin_assisted_logins' => $adminAssistedLogins,
            ],
            'topUsers' => $topUsers,
            'recentActivities' => $recentActivities,
        ]);
    }
}

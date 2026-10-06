<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\LoginActivity;
use App\Models\SimulationActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Tracker/Index', [
            'summary' => $this->loginSummary(),
            'topUsers' => $this->topUsers(),
            'recentActivities' => $this->recentActivities(),
            'simulationSummary' => $this->simulationSummary(),
            'topStudents' => $this->topStudents(),
            'recentSimulations' => $this->recentSimulations(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function loginSummary(): array
    {
        return [
            'total_logins' => LoginActivity::count(),
            'today_logins' => LoginActivity::whereDate('created_at', today())->count(),
            'week_logins' => LoginActivity::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_users' => LoginActivity::query()->distinct()->count('user_id'),
            'admin_assisted_logins' => LoginActivity::whereNotNull('actor_user_id')->count(),
        ];
    }

    protected function topUsers()
    {
        return User::query()
            ->select('id', 'name', 'email', 'username', 'role')
            ->withCount('loginActivities')
            ->withMax('loginActivities', 'created_at')
            ->get()
            ->filter(fn (User $user) => ($user->login_activities_count ?? 0) > 0)
            ->sortByDesc('login_activities_count')
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
                'login_count' => $user->login_activities_count ?? 0,
                'last_login_at' => $user->login_activities_max_created_at,
            ]);
    }

    protected function recentActivities()
    {
        return LoginActivity::with([
            'user:id,name,email,username,role',
            'actor:id,name,email,username,role',
        ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (LoginActivity $activity) => [
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
            ]);
    }

    /**
     * @return array<string, int>
     */
    protected function simulationSummary(): array
    {
        return [
            'total_simulations' => SimulationActivity::count(),
            'today_simulations' => SimulationActivity::whereDate('created_at', today())->count(),
            'week_simulations' => SimulationActivity::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_students' => SimulationActivity::query()->distinct()->count('user_id'),
        ];
    }

    protected function topStudents()
    {
        $rows = SimulationActivity::query()
            ->selectRaw('user_id, learner_id, count(*) as simulation_count, max(created_at) as last_simulation_at')
            ->groupBy('user_id', 'learner_id')
            ->orderByDesc('simulation_count')
            ->limit(10)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->all())
            ->get(['id', 'name', 'email', 'username', 'role'])
            ->keyBy('id');

        $learners = Learner::query()
            ->whereIn('id', $rows->pluck('learner_id')->filter()->unique()->all())
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'user_id'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users, $learners) {
            $learner = $learners->get($row->learner_id);
            $user = $users->get($row->user_id) ?? ($learner ? $users->get($learner->user_id) : null);

            $name = $learner
                ? trim(collect([$learner->last_name, $learner->first_name])->filter()->implode(', '))
                : ($user?->name ?? 'Unknown student');

            return [
                'id' => $row->user_id ?? $row->learner_id,
                'name' => $name,
                'username' => $user?->username ?? $user?->email,
                'role' => $user?->role ?? 'student',
                'simulation_count' => (int) $row->simulation_count,
                'last_simulation_at' => $row->last_simulation_at,
            ];
        })->values();
    }

    protected function recentSimulations()
    {
        return SimulationActivity::with([
            'user:id,name,email,username,role',
            'learner:id,first_name,middle_name,last_name',
            'section:id,section_name',
        ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (SimulationActivity $activity) {
                $learner = $activity->learner;
                $name = $learner
                    ? trim(collect([$learner->last_name, $learner->first_name])->filter()->implode(', '))
                    : ($activity->user?->name ?? 'Unknown student');

                return [
                    'id' => $activity->id,
                    'student' => $name,
                    'username' => $activity->user?->username ?: $activity->user?->email,
                    'role' => $activity->user?->role ?? 'student',
                    'section' => $activity->section?->section_name,
                    'quarter' => $activity->quarter,
                    'created_at' => $activity->created_at?->toISOString(),
                ];
            });
    }
}

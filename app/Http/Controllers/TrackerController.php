<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\LoginActivity;
use App\Models\SchoolYear;
use App\Models\SimulationActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $notSimulatedStudents = $this->notSimulatedStudents();

        return Inertia::render('Tracker/Index', [
            'filters' => $filters,
            'summary' => $this->loginSummary($filters),
            'topUsers' => $this->topUsers($filters),
            'recentActivities' => $this->recentActivities($filters),
            'simulationSummary' => $this->simulationSummary($notSimulatedStudents->count(), $filters),
            'notSimulatedStudents' => $notSimulatedStudents,
            'topStudents' => $this->topStudents($filters),
            'recentSimulations' => $this->recentSimulations($filters),
        ]);
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    protected function resolveFilters(Request $request): array
    {
        return [
            'from' => $this->validDate($request->query('from')),
            'to' => $this->validDate($request->query('to')),
        ];
    }

    protected function validDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    protected function applyDateRange(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'], fn (Builder $inner) => $inner->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $inner) => $inner->whereDate('created_at', '<=', $filters['to']));
    }

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     * @return array<string, int>
     */
    protected function loginSummary(array $filters): array
    {
        $query = $this->applyDateRange(LoginActivity::query(), $filters);

        return [
            'total_logins' => (clone $query)->count(),
            'today_logins' => LoginActivity::whereDate('created_at', today())->count(),
            'week_logins' => LoginActivity::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_users' => (clone $query)->distinct()->count('user_id'),
            'admin_assisted_logins' => (clone $query)->whereNotNull('actor_user_id')->count(),
        ];
    }

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    protected function topUsers(array $filters)
    {
        $rows = $this->applyDateRange(LoginActivity::query(), $filters)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as login_count, max(created_at) as last_login_at')
            ->groupBy('user_id')
            ->orderByDesc('login_count')
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->unique()->all())
            ->get(['id', 'name', 'email', 'username', 'role'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);

            return [
                'id' => $row->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'username' => $user?->username,
                'role' => $user?->role,
                'login_count' => (int) $row->login_count,
                'last_login_at' => $row->last_login_at,
            ];
        })->values();
    }

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    protected function recentActivities(array $filters)
    {
        return $this->applyDateRange(LoginActivity::with([
            'user:id,name,email,username,role',
            'actor:id,name,email,username,role',
        ]), $filters)
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
     * @param  array{from: ?string, to: ?string}  $filters
     * @return array<string, int>
     */
    protected function simulationSummary(int $notSimulatedStudents, array $filters): array
    {
        $query = $this->applyDateRange(SimulationActivity::query(), $filters);

        return [
            'total_simulations' => (clone $query)->count(),
            'today_simulations' => SimulationActivity::whereDate('created_at', today())->count(),
            'week_simulations' => SimulationActivity::where('created_at', '>=', now()->startOfWeek())->count(),
            'unique_students' => (clone $query)->distinct()->count('user_id'),
            'not_simulated_students' => $notSimulatedStudents,
        ];
    }

    /**
     * Active learners in the current school year who have no simulation
     * activity recorded. Matching falls back to the learner's linked user
     * because a simulation row may carry only a user_id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function notSimulatedStudents()
    {
        $schoolYear = SchoolYear::current();

        if (! $schoolYear) {
            return collect();
        }

        $simulatedLearnerIds = SimulationActivity::query()
            ->whereNotNull('learner_id')
            ->pluck('learner_id');

        $simulatedUserIds = SimulationActivity::query()
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $enrollments = Enrollment::with(['learner', 'section'])
            ->where('enrollments.school_year_id', $schoolYear->id)
            ->where('enrollments.status', 'active')
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->select('enrollments.*')
            ->get();

        return $enrollments
            ->reject(function (Enrollment $enrollment) use ($simulatedLearnerIds, $simulatedUserIds) {
                $learner = $enrollment->learner;

                if (! $learner) {
                    return true;
                }

                return $simulatedLearnerIds->contains($learner->id)
                    || ($learner->user_id !== null && $simulatedUserIds->contains($learner->user_id));
            })
            ->map(function (Enrollment $enrollment) {
                $learner = $enrollment->learner;

                return [
                    'id' => $learner->id,
                    'name' => trim(collect([$learner->last_name, $learner->first_name])->filter()->implode(', ')),
                    'username' => $learner->email,
                    'section' => $enrollment->section?->section_name,
                ];
            })
            ->values();
    }

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    protected function topStudents(array $filters)
    {
        $rows = $this->applyDateRange(SimulationActivity::query(), $filters)
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

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    protected function recentSimulations(array $filters)
    {
        return $this->applyDateRange(SimulationActivity::with([
            'user:id,name,email,username,role',
            'learner:id,first_name,middle_name,last_name',
            'section:id,section_name',
        ]), $filters)
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

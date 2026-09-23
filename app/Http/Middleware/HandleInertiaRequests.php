<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $impersonatorId = $request->session()->get('impersonator_user_id');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'impersonation' => $impersonatorId ? [
                    'active' => true,
                    'admin_name' => $request->session()->get('impersonator_user_name'),
                ] : [
                    'active' => false,
                    'admin_name' => null,
                ],
            ],
        ];
    }
}

<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use Illuminate\Auth\Events\Login;

class RecordLoginActivity
{
    public function handle(Login $event): void
    {
        $request = request();

        if (! $request?->hasSession()) {
            return;
        }

        if ($request->session()->pull('suppress_login_tracking', false)) {
            $request->session()->forget('login_tracker_actor_user_id');
            return;
        }

        $actorUserId = $request->session()->pull('login_tracker_actor_user_id');
        $user = $event->user;

        LoginActivity::create([
            'user_id' => $user->id,
            'actor_user_id' => $actorUserId ?: null,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_username' => $user->username ?? null,
            'user_role' => $user->role ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}

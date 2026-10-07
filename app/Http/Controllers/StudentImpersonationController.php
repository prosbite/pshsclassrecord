<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StudentImpersonationController extends Controller
{
    /**
     * Default password assigned when an admin resets a student's password.
     */
    private const DEFAULT_STUDENT_PASSWORD = '12345678';

    public function store(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $admin = $request->user();
        $studentUser = $enrollment->loadMissing('learner.user')->learner?->user;

        if (! $studentUser) {
            return redirect()
                ->route('students')
                ->with('error', 'This student does not have an account yet.');
        }

        $request->session()->put('impersonator_user_id', $admin?->id);
        $request->session()->put('impersonator_user_name', $admin?->name);
        $request->session()->put('login_tracker_actor_user_id', $admin?->id);

        Auth::login($studentUser);
        $request->session()->regenerate();

        return redirect()
            ->route('student.dashboard')
            ->with('success', "Signed in as {$studentUser->name}.");
    }

    public function resetPassword(Enrollment $enrollment): RedirectResponse
    {
        $studentUser = $enrollment->loadMissing('learner.user')->learner?->user;

        if (! $studentUser) {
            return redirect()
                ->route('students')
                ->with('error', 'This student does not have an account yet.');
        }

        $studentUser->password = Hash::make(self::DEFAULT_STUDENT_PASSWORD);
        $studentUser->save();

        return redirect()
            ->route('students')
            ->with('success', "Password for {$studentUser->name} has been reset to the default.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_user_id');
        $impersonatorName = $request->session()->pull('impersonator_user_name');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('error', 'Unable to restore the admin session.');
        }

        $request->session()->put('suppress_login_tracking', true);
        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget('suppress_login_tracking');

        return redirect()
            ->route('dashboard')
            ->with('success', $impersonatorName ? "Returned to {$impersonatorName}'s admin session." : 'Returned to admin session.');
    }
}

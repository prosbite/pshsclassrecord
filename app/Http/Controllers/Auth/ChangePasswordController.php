<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ChangePasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Auth/ChangePassword', [
            'status' => session('status'),
        ]);
    }
}

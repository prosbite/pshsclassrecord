<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Edit', [
            'passingThreshold' => Setting::passingThreshold(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'passing_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::set('passing_threshold', $data['passing_threshold']);

        return redirect()->route('settings.edit')->with('success', 'Settings updated');
    }
}

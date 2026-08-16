<?php

namespace App\Http\Controllers;

use App\Support\PbnSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = PbnSettings::all();
        return view('settings.index', [
            'api_timeout_seconds' => $settings['api_timeout_seconds'],
            'delete_timeout_seconds' => $settings['delete_timeout_seconds'],
            'link_delay_seconds' => $settings['link_delay_seconds'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_timeout_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'delete_timeout_seconds' => ['required', 'integer', 'min:60', 'max:1800'],
            'link_delay_seconds' => ['required', 'integer', 'min:0', 'max:300'],
        ], [
            'api_timeout_seconds.required' => 'API timeout is required.',
            'api_timeout_seconds.min' => 'API timeout must be at least 10 seconds.',
            'api_timeout_seconds.max' => 'API timeout may not exceed 600 seconds.',
            'delete_timeout_seconds.required' => 'Delete timeout is required.',
            'delete_timeout_seconds.min' => 'Delete timeout must be at least 60 seconds.',
            'delete_timeout_seconds.max' => 'Delete timeout may not exceed 1800 seconds.',
            'link_delay_seconds.required' => 'Delay between jobs is required.',
            'link_delay_seconds.min' => 'Delay cannot be negative.',
            'link_delay_seconds.max' => 'Delay may not exceed 300 seconds.',
        ]);

        PbnSettings::set([
            'api_timeout_seconds' => (int) $validated['api_timeout_seconds'],
            'delete_timeout_seconds' => (int) $validated['delete_timeout_seconds'],
            'link_delay_seconds' => (int) $validated['link_delay_seconds'],
        ]);

        return redirect()->route('settings.index')->with('success', 'Settings saved. API timeout and rate limiting will apply to the next requests.');
    }
}

# Add this to your target domain's routes/api.php

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Hidden Links Toggle API
Route::middleware('auth:sanctum')->post('/hidden-links/toggle-visibility', function (Request $request) {
    $validated = $request->validate([
        'show_hidden_links' => ['nullable', 'boolean'],
        'action' => ['nullable', 'string', 'in:toggle'],
    ]);

    $settings = \App\Models\Setting::first();

    if (!$settings) {
        return response()->json([
            'status' => false,
            'message' => 'Settings not found. Please initialize settings first.',
        ], 404);
    }

    // If specific value is provided, use it
    if (isset($validated['show_hidden_links'])) {
        $settings->show_hidden_links = $validated['show_hidden_links'];
    }
    // If action is toggle, flip the current value
    elseif (isset($validated['action']) && $validated['action'] === 'toggle') {
        $settings->show_hidden_links = !$settings->show_hidden_links;
    }
    // Default: toggle if no parameters provided
    else {
        $settings->show_hidden_links = !$settings->show_hidden_links;
    }

    $settings->save();

    // Clear cache if you're using cache
    if (method_exists(\App\Models\Setting::class, 'clearCache')) {
        \App\Models\Setting::clearCache();
    }

    return response()->json([
        'status' => true,
        'message' => 'Hidden links visibility updated successfully.',
        'show_hidden_links' => (bool) $settings->show_hidden_links,
    ]);
});

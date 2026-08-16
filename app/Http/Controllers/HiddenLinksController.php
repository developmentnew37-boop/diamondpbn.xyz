<?php

namespace App\Http\Controllers;

use App\Jobs\ToggleHiddenLinksJob;
use App\Models\CampaignDomain;
use App\Support\PbnSettings;
use Illuminate\Http\Request;

class HiddenLinksController extends Controller
{
    public function index()
    {
        $currentStatus = PbnSettings::getShowHiddenLinks();

        $activeDomainCount = CampaignDomain::where('user_id', auth()->id())
            ->where('status', 'active')
            ->count();

        $domains = CampaignDomain::where('user_id', auth()->id())
            ->where('status', 'active')
            ->orderBy('domain')
            ->paginate(50);

        return view('hidden-links.index', compact('currentStatus', 'domains', 'activeDomainCount'));
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'show_hidden_links' => 'required|boolean',
        ]);

        $showHiddenLinks = $validated['show_hidden_links'];

        // Get all active campaign domains
        $domains = CampaignDomain::where('user_id', auth()->id())
            ->where('status', 'active')
            ->get();

        if ($domains->isEmpty()) {
            return back()->with('error', 'No active target domains found. Please add domains first.');
        }

        // Save the setting locally
        PbnSettings::setShowHiddenLinks($showHiddenLinks);

        // Dispatch jobs for each domain
        foreach ($domains as $domain) {
            ToggleHiddenLinksJob::dispatch($domain, $showHiddenLinks);
        }

        $action = $showHiddenLinks ? 'show' : 'hide';
        $message = "Queued {$domains->count()} domain(s) to {$action} hidden links. The changes will be applied shortly.";

        return back()->with('success', $message);
    }
}

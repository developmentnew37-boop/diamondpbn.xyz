<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Campaign;
use App\Models\CampaignDomain;
use App\Models\Domain;
use App\Models\WpBatch;
use App\Models\WpSite;
use App\Support\Workspace;

class DashboardController extends Controller
{
    public function index()
    {
        $ownerId = Workspace::ownerId();

        $stats = [
            'domains' => Domain::where('user_id', $ownerId)->count(),
            'batches' => Workspace::scopeOwnPosting(Batch::query())->count(),
            'links_posted' => Workspace::scopeOwnPosting(Batch::query())->sum('success_count'),
            'active_batches' => Workspace::scopeOwnPosting(Batch::query())
                ->whereIn('status', ['pending', 'processing'])->count(),
            'campaigns' => Workspace::scopeOwnPosting(Campaign::query())->count(),
            'campaign_domains' => CampaignDomain::where('user_id', $ownerId)->count(),
            'campaign_links_posted' => Workspace::scopeOwnPosting(Campaign::query())->sum('success_count'),
            'active_campaigns' => Workspace::scopeOwnPosting(Campaign::query())
                ->whereIn('status', ['pending', 'processing'])->count(),
            'wp_sites' => WpSite::where('user_id', $ownerId)->count(),
            'wp_batches' => Workspace::scopeOwnPosting(WpBatch::query())->count(),
            'wp_links_posted' => Workspace::scopeOwnPosting(WpBatch::query())->sum('success_count'),
            'active_wp_batches' => Workspace::scopeOwnPosting(WpBatch::query())
                ->whereIn('status', ['pending', 'processing'])->count(),
        ];

        $recentBatches = Workspace::scopeOwnPosting(Batch::query())->latest()->take(5)->get();
        $recentCampaigns = Workspace::scopeOwnPosting(Campaign::query())->latest()->take(5)->get();
        $recentWpBatches = Workspace::scopeOwnPosting(WpBatch::query())->latest()->take(5)->get();

        return view('dashboard.index', compact('stats', 'recentBatches', 'recentCampaigns', 'recentWpBatches'));
    }
}

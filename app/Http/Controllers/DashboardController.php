<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Campaign;
use App\Models\CampaignDomain;
use App\Models\Domain;
use App\Models\WpBatch;
use App\Models\WpSite;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $stats = [
            'domains' => Domain::where('user_id', $userId)->count(),
            'batches' => Batch::where('user_id', $userId)->count(),
            'links_posted' => Batch::where('user_id', $userId)->sum('success_count'),
            'active_batches' => Batch::where('user_id', $userId)
                ->whereIn('status', ['pending', 'processing'])->count(),
            'campaigns' => Campaign::where('user_id', $userId)->count(),
            'campaign_domains' => CampaignDomain::where('user_id', $userId)->count(),
            'campaign_links_posted' => Campaign::where('user_id', $userId)->sum('success_count'),
            'active_campaigns' => Campaign::where('user_id', $userId)
                ->whereIn('status', ['pending', 'processing'])->count(),
            'wp_sites' => WpSite::where('user_id', $userId)->count(),
            'wp_batches' => WpBatch::where('user_id', $userId)->count(),
            'wp_links_posted' => WpBatch::where('user_id', $userId)->sum('success_count'),
            'active_wp_batches' => WpBatch::where('user_id', $userId)
                ->whereIn('status', ['pending', 'processing'])->count(),
        ];

        $recentBatches = Batch::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        $recentCampaigns = Campaign::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        $recentWpBatches = WpBatch::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', compact('stats', 'recentBatches', 'recentCampaigns', 'recentWpBatches'));
    }
}

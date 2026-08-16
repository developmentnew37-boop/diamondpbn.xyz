<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Campaign;
use App\Models\CampaignDomain;
use App\Models\Domain;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'domains' => Domain::where('user_id', auth()->id())->count(),
            'batches' => Batch::where('user_id', auth()->id())->count(),
            'links_posted' => Batch::where('user_id', auth()->id())->sum('success_count'),
            'active_batches' => Batch::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])->count(),
            'campaigns' => Campaign::where('user_id', auth()->id())->count(),
            'campaign_domains' => CampaignDomain::where('user_id', auth()->id())->count(),
            'campaign_links_posted' => Campaign::where('user_id', auth()->id())->sum('success_count'),
            'active_campaigns' => Campaign::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])->count(),
        ];

        $recentBatches = Batch::where('user_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        $recentCampaigns = Campaign::where('user_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', compact('stats', 'recentBatches', 'recentCampaigns'));
    }
}

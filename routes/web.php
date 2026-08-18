<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\WpBatchController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignDomainController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\WpBlockInspectController;
use App\Http\Controllers\WpSiteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/dashboard', '/', 301);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::get('/domains/export', [DomainController::class, 'export'])->name('domains.export');
    Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}/edit', [DomainController::class, 'edit'])->name('domains.edit');
    Route::patch('/domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
    Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::post('/domains/{domain}/recheck', [DomainController::class, 'recheck'])->name('domains.recheck');
    Route::post('/domains/check-all', [DomainController::class, 'checkAllHealth'])->name('domains.check-all');
    Route::post('/domains/bulk-delete', [DomainController::class, 'destroyBulk'])->name('domains.bulk-destroy');
    Route::post('/domains/import', [DomainController::class, 'import'])->name('domains.import');
    Route::delete('/domains/imports/{domainImport}', [DomainController::class, 'destroyImport'])->name('domains.imports.destroy');
    Route::delete('/domains/imports/{domainImport}/domains', [DomainController::class, 'destroyImportDomains'])->name('domains.imports.destroy-domains');
    Route::get('/wp-sites', [WpSiteController::class, 'index'])->name('wp-sites.index');
    Route::get('/wp-sites/export', [WpSiteController::class, 'export'])->name('wp-sites.export');
    Route::get('/wp-sites/block-inspect', [WpBlockInspectController::class, 'index'])->name('wp-sites.block-inspect');
    Route::post('/wp-sites/block-inspect/toggle-all', [WpBlockInspectController::class, 'toggleAll'])->name('wp-sites.block-inspect.toggle-all');
    Route::post('/wp-sites/block-inspect/toggle-selected', [WpBlockInspectController::class, 'toggleSelected'])->name('wp-sites.block-inspect.toggle-selected');
    Route::post('/wp-sites/block-inspect/toggle-manual', [WpBlockInspectController::class, 'toggleManual'])->name('wp-sites.block-inspect.toggle-manual');
    Route::post('/wp-sites', [WpSiteController::class, 'store'])->name('wp-sites.store');
    Route::get('/wp-sites/{wpSite}/edit', [WpSiteController::class, 'edit'])->name('wp-sites.edit');
    Route::patch('/wp-sites/{wpSite}', [WpSiteController::class, 'update'])->name('wp-sites.update');
    Route::delete('/wp-sites/{wpSite}', [WpSiteController::class, 'destroy'])->name('wp-sites.destroy');
    Route::post('/wp-sites/{wpSite}/recheck', [WpSiteController::class, 'recheck'])->name('wp-sites.recheck');
    Route::post('/wp-sites/{wpSite}/toggle-inspect', [WpBlockInspectController::class, 'toggleSite'])->name('wp-sites.toggle-inspect');
    Route::post('/wp-sites/check-all', [WpSiteController::class, 'checkAllHealth'])->name('wp-sites.check-all');
    Route::post('/wp-sites/bulk-delete', [WpSiteController::class, 'destroyBulk'])->name('wp-sites.bulk-destroy');
    Route::post('/wp-sites/import', [WpSiteController::class, 'import'])->name('wp-sites.import');
    Route::delete('/wp-sites/imports/{wpSiteImport}', [WpSiteController::class, 'destroyImport'])->name('wp-sites.imports.destroy');
    Route::delete('/wp-sites/imports/{wpSiteImport}/sites', [WpSiteController::class, 'destroyImportSites'])->name('wp-sites.imports.destroy-sites');
    Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/create', [BatchController::class, 'create'])->name('batches.create');
    Route::post('/batches', [BatchController::class, 'store'])->name('batches.store');
    Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::get('/batches/{batch}/export-domains', [BatchController::class, 'exportDomains'])->name('batches.export-domains');
    Route::post('/batches/{batch}/domains/remove-problem', [BatchController::class, 'removeProblemDomains'])->name('batches.domains.remove-problem');
    Route::delete('/batches/{batch}/domains/{domain}', [BatchController::class, 'destroyDomain'])->name('batches.domains.destroy');
    Route::get('/batches/{batch}/domains/{domain}', [BatchController::class, 'showDomain'])->name('batches.show-domain');
    Route::delete('/batches/{batch}/links/{link}', [BatchController::class, 'destroyLink'])->name('batches.links.destroy');
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');
    Route::post('/batches/{batch}/retry-failed', [BatchController::class, 'retryFailed'])->name('batches.retry-failed');
    Route::post('/batches/{batch}/publish-pending', [BatchController::class, 'publishPending'])->name('batches.publish-pending');
    Route::get('/wp-batches', [WpBatchController::class, 'index'])->name('wp-batches.index');
    Route::get('/wp-batches/create', [WpBatchController::class, 'create'])->name('wp-batches.create');
    Route::post('/wp-batches', [WpBatchController::class, 'store'])->name('wp-batches.store');
    Route::get('/wp-batches/{wpBatch}', [WpBatchController::class, 'show'])->name('wp-batches.show');
    Route::get('/wp-batches/{wpBatch}/export-domains', [WpBatchController::class, 'exportDomains'])->name('wp-batches.export-domains');
    Route::post('/wp-batches/{wpBatch}/domains/remove-problem', [WpBatchController::class, 'removeProblemDomains'])->name('wp-batches.domains.remove-problem');
    Route::delete('/wp-batches/{wpBatch}/domains/{wpSite}', [WpBatchController::class, 'destroyDomain'])->name('wp-batches.domains.destroy');
    Route::get('/wp-batches/{wpBatch}/domains/{wpSite}', [WpBatchController::class, 'showDomain'])->name('wp-batches.show-domain');
    Route::delete('/wp-batches/{wpBatch}/links/{wpLink}', [WpBatchController::class, 'destroyLink'])->name('wp-batches.links.destroy');
    Route::delete('/wp-batches/{wpBatch}', [WpBatchController::class, 'destroy'])->name('wp-batches.destroy');
    Route::post('/wp-batches/{wpBatch}/retry-failed', [WpBatchController::class, 'retryFailed'])->name('wp-batches.retry-failed');
    Route::post('/wp-batches/{wpBatch}/publish-pending', [WpBatchController::class, 'publishPending'])->name('wp-batches.publish-pending');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::middleware('admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/hidden-links', [\App\Http\Controllers\HiddenLinksController::class, 'index'])->name('hidden-links.index');
        Route::post('/hidden-links/toggle', [\App\Http\Controllers\HiddenLinksController::class, 'toggle'])->name('hidden-links.toggle');
    });

    // Campaign Domains
    Route::get('/campaign-domains', [CampaignDomainController::class, 'index'])->name('campaign-domains.index');
    Route::get('/campaign-domains/export', [CampaignDomainController::class, 'export'])->name('campaign-domains.export');
    Route::post('/campaign-domains', [CampaignDomainController::class, 'store'])->name('campaign-domains.store');
    Route::post('/campaign-domains/import', [CampaignDomainController::class, 'import'])->name('campaign-domains.import');
    Route::post('/campaign-domains/check-all', [CampaignDomainController::class, 'checkAllHealth'])->name('campaign-domains.check-all');
    Route::delete('/campaign-domains/imports/{domainImport}', [CampaignDomainController::class, 'destroyImport'])->name('campaign-domains.imports.destroy');
    Route::delete('/campaign-domains/imports/{domainImport}/domains', [CampaignDomainController::class, 'destroyImportDomains'])->name('campaign-domains.imports.destroy-domains');
    Route::get('/campaign-domains/{campaignDomain}/edit', [CampaignDomainController::class, 'edit'])->name('campaign-domains.edit');
    Route::patch('/campaign-domains/{campaignDomain}', [CampaignDomainController::class, 'update'])->name('campaign-domains.update');
    Route::delete('/campaign-domains/{campaignDomain}', [CampaignDomainController::class, 'destroy'])->name('campaign-domains.destroy');
    Route::post('/campaign-domains/{campaignDomain}/check', [CampaignDomainController::class, 'checkHealth'])->name('campaign-domains.check');
    Route::post('/campaign-domains/bulk-delete', [CampaignDomainController::class, 'destroyBulk'])->name('campaign-domains.bulk-destroy');

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/domains/remove-problem', [CampaignController::class, 'removeProblemDomains'])->name('campaigns.domains.remove-problem');
    Route::delete('/campaigns/{campaign}/domains/{campaignDomain}', [CampaignController::class, 'destroyDomain'])->name('campaigns.domains.destroy');
    Route::get('/campaigns/{campaign}/domains/{campaignDomain}', [CampaignController::class, 'showDomain'])->name('campaigns.show-domain');
    Route::delete('/campaigns/{campaign}/links/{link}', [CampaignController::class, 'destroyLink'])->name('campaigns.links.destroy');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('/campaigns/{campaign}/publish-pending', [CampaignController::class, 'publishPending'])->name('campaigns.publish-pending');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

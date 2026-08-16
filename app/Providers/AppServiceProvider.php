<?php

namespace App\Providers;

use App\Models\WpBatch;
use App\Models\WpLink;
use App\Models\WpSite;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('wpBatch', fn (string $value) => WpBatch::findOrFail($value));
        Route::bind('wpSite', fn (string $value) => WpSite::findOrFail($value));
        Route::bind('wpLink', fn (string $value) => WpLink::findOrFail($value));
    }
}

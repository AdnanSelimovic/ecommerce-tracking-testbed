<?php

namespace App\Providers;

use App\Tracking\ClientTrackingDelivery;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('clientTracking', app(ClientTrackingDelivery::class)->pullBrowserBootstrap());
        });
    }
}

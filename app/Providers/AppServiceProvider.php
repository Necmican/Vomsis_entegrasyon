<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- SİHİRLİ SATIRIMIZ BURADA
use Illuminate\Pagination\Paginator; // <-- SAYFALAMA DÜZELTİCİ EKLENDİ

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
        if (config('app.env') !== 'local' || request()->server('HTTP_X_FORWARDED_PROTO') == 'https') {
            URL::forceScheme('https');
        }
        
        // Laravel varsayılan Tailwind sayfalamasını projedeki Bootstrap 5 temasına uyduruyoruz
        Paginator::useBootstrapFive();
    }
}
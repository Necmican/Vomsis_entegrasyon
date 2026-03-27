<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- SİHİRLİ SATIRIMIZ BURADA

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
        // NGROK KULLANILIYORSA SİSTEMİ ZORLA HTTPS YAP
        if (str_contains(config('app.url'), 'ngrok')) {
            URL::forceScheme('https');
        }
    }
}
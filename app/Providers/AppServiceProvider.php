<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CityFunction;
use App\Observers\CityFunctionObserver;

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
        CityFunction::observe(CityFunctionObserver::class);
    }
}

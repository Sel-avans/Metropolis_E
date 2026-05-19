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
public function boot()
{
    if (!app()->runningInConsole()) {
       // \App\Models\CityFunction::observe(\App\Observers\CityFunctionObserver::class);
    }
}
}
<?php

namespace App\Providers;

use App\Models\CityFunction;
use App\Observers\CityFunctionObserver;
use App\Policies\PagePolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;

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
        Gate::before(function (User $user, string $ability): ?bool {
            $policy = new PagePolicy();

            if (method_exists($policy, $ability)) {
                return $policy->$ability($user);
            }

            return null;
        });

        if (!app()->runningInConsole()) {
            // \App\Models\CityFunction::observe(\App\Observers\CityFunctionObserver::class);
        }

        
        Gate::define('CanManageEvents', [PagePolicy::class, 'CanManageEvents']);

        View::composer('*', function ($view): void {
            if (!isset($view->getData()['errors'])) {
                $view->with('errors', session()->get('errors') ?? new ViewErrorBag());
            }
        });
    }
}

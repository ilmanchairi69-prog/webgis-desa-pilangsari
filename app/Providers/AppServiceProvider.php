<?php

namespace App\Providers;

use App\Services\VillageBoundaryValidator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(VillageBoundaryValidator::class, fn (): VillageBoundaryValidator => new VillageBoundaryValidator);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('layouts.pagination');
    }
}

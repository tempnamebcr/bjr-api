<?php

namespace App\Providers;

use App\Repositories\DatasetRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(DatasetRepository::class)
            ->needs('$directory')
            ->give(fn (): string => resource_path('datasets'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

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
        $this->app->bind(DatasetRepository::class, fn (): DatasetRepository => new DatasetRepository(
            resource_path(DatasetRepository::SAMPLE_EMPLOYEES),
            resource_path(DatasetRepository::SAMPLE_TASKS),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Contracts\SolvesAssignments;
use App\Repositories\DatasetRepository;
use App\Services\CachedAssignmentSolver;
use Illuminate\Contracts\Cache\Repository as Cache;
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

        // Callers depend on the interface; the cache wraps the real solver.
        $this->app->singleton(SolvesAssignments::class, fn ($app): SolvesAssignments => new CachedAssignmentSolver(
            $app->make(AssignmentSolver::class),
            $app->make(Cache::class),
            (int) config('assignment.cache_ttl'),
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

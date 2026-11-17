<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Enums\Variant;
use App\Repositories\DatasetRepository;
use RuntimeException;
use Tests\TestCase;

/**
 * The brief's own data, end to end through the container. These numbers are the ones
 * quoted in CLAUDE.md and the README, so a regression in either strategy shows up here.
 */
final class SampleDatasetTest extends TestCase
{
    public function test_the_bundled_dataset_loads(): void
    {
        $dataset = $this->app->make(DatasetRepository::class);

        $this->assertCount(10, $dataset->employees());
        $this->assertCount(30, $dataset->tasks());
    }

    public function test_min_cost_on_the_sample_data(): void
    {
        $result = $this->solve(Variant::Cost);

        // L1 tasks (27h) → employee 8 at 80/h; L2+L3 tasks (51h) → employee 4 at 300/h.
        $this->assertSame(17460.0, $result->totalPrice);
        $this->assertSame(51.0, $result->makespan);
        $this->assertCount(30, $result->assignments);
    }

    public function test_min_makespan_on_the_sample_data_reaches_the_lower_bound(): void
    {
        $result = $this->solve(Variant::Makespan);

        // 51h of L2+ work over 5 capable employees = 10.2 → 11 with whole hours.
        $this->assertSame(11.0, $result->lowerBound);
        $this->assertSame(11.0, $result->makespan);
        $this->assertTrue($result->isProvenOptimalMakespan());
        $this->assertSame(25050.0, $result->totalPrice);
        $this->assertCount(30, $result->assignments);
    }

    public function test_a_missing_dataset_directory_fails_clearly(): void
    {
        $this->expectException(RuntimeException::class);

        (new DatasetRepository(__DIR__.'/does-not-exist'))->employees();
    }

    private function solve(Variant $variant): AssignmentResult
    {
        $dataset = $this->app->make(DatasetRepository::class);

        return $this->app->make(AssignmentSolver::class)->solve($dataset->tasks(), $dataset->employees(), $variant);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Brief edge case iv: 50–200 tasks, 5–30 employees. Instances come from a seeded generator,
 * so every run tests exactly the same inputs and failures are reproducible.
 */
final class AssignmentSolverPerformanceTest extends TestCase
{
    private const TIME_LIMIT_SECONDS = 1.0;

    /** @return array<string, array{int, int, int}> */
    public static function instances(): array
    {
        return [
            '50 tasks × 5 employees' => [50, 5, 1],
            '100 tasks × 15 employees' => [100, 15, 2],
            '200 tasks × 5 employees' => [200, 5, 3],
            '200 tasks × 30 employees' => [200, 30, 4],
        ];
    }

    #[DataProvider('instances')]
    public function test_both_variants_solve_within_the_time_limit(int $taskCount, int $employeeCount, int $seed): void
    {
        [$tasks, $employees] = $this->generate($taskCount, $employeeCount, $seed);
        $solver = new AssignmentSolver;

        foreach (Variant::cases() as $variant) {
            $started = hrtime(true);
            $result = $solver->solve($tasks, $employees, $variant);
            $seconds = (hrtime(true) - $started) / 1e9;

            // Printed so the README / interview can quote real numbers.
            fwrite(STDERR, sprintf("\n  %-26s %-8s %7.1f ms  makespan %6.1f  LB %6.1f", $this->dataName(), $variant->value, $seconds * 1000, $result->makespan, $result->lowerBound));

            $this->assertTrue($result->isFeasible());
            $this->assertCount($taskCount, $result->assignments);
            $this->assertGreaterThanOrEqual($result->lowerBound - 1e-9, $result->makespan);
            $this->assertLessThan(self::TIME_LIMIT_SECONDS, $seconds, "{$variant->value} took {$seconds}s");
        }
    }

    public function test_identical_input_gives_identical_output_regardless_of_order(): void
    {
        [$tasks, $employees] = $this->generate(200, 30, 42);
        $solver = new AssignmentSolver;
        $shuffler = new Randomizer(new Mt19937(7));

        foreach (Variant::cases() as $variant) {
            $first = $solver->solve($tasks, $employees, $variant);
            $again = $solver->solve($tasks, $employees, $variant);
            $shuffled = $solver->solve($shuffler->shuffleArray($tasks), $shuffler->shuffleArray($employees), $variant);

            $this->assertEquals($first, $again);
            $this->assertEquals($first, $shuffled);
        }
    }

    /**
     * Mixed skills (one top-skill employee guaranteed, so every instance is feasible),
     * rates from 50 to 1000 and durations from 1 to 12 hours.
     *
     * @return array{list<Task>, list<Employee>}
     */
    private function generate(int $taskCount, int $employeeCount, int $seed): array
    {
        $random = new Randomizer(new Mt19937($seed));

        $employees = [new Employee(1, 3, $random->getInt(50, 1000))];
        for ($id = 2; $id <= $employeeCount; $id++) {
            $employees[] = new Employee($id, $random->getInt(1, 3), $random->getInt(50, 1000));
        }

        $tasks = [];
        for ($id = 1; $id <= $taskCount; $id++) {
            $tasks[] = new Task($id, $random->getInt(1, 3), $random->getInt(1, 12));
        }

        return [$tasks, $employees];
    }
}

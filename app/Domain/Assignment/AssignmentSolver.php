<?php

declare(strict_types=1);

namespace App\Domain\Assignment;

use App\Domain\Assignment\Contracts\AssignmentStrategy;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Domain\Assignment\Strategies\MinCostStrategy;
use App\Domain\Assignment\Strategies\MinMakespanStrategy;
use App\Domain\Assignment\Support\FeasibilityChecker;
use App\Domain\Assignment\Support\ResultBuilder;
use InvalidArgumentException;

/**
 * Entry point of the domain (the "service" in service–repository terms):
 * validate ids → check feasibility → run the variant's strategy → measure the result.
 */
final readonly class AssignmentSolver
{
    public function __construct(
        private FeasibilityChecker $feasibility = new FeasibilityChecker,
        private ResultBuilder $resultBuilder = new ResultBuilder,
        private MinCostStrategy $minCost = new MinCostStrategy,
        private MinMakespanStrategy $minMakespan = new MinMakespanStrategy,
    ) {}

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     *
     * @throws InvalidArgumentException when task or employee ids are not unique
     */
    public function solve(array $tasks, array $employees, Variant $variant): AssignmentResult
    {
        $this->assertUniqueIds(array_map(fn (Task $task): int => $task->id, $tasks), 'task');
        $this->assertUniqueIds(array_map(fn (Employee $employee): int => $employee->id, $employees), 'employee');

        // Canonical order on entry: the result can't depend on how the input was ordered.
        usort($tasks, fn (Task $a, Task $b): int => $a->id <=> $b->id);
        usort($employees, fn (Employee $a, Employee $b): int => $a->id <=> $b->id);

        $unassignable = $this->feasibility->unassignableTaskIds($tasks, $employees);
        if ($unassignable !== []) {
            return AssignmentResult::infeasible($variant, $unassignable);
        }

        $assignment = $this->strategyFor($variant)->assign($tasks, $employees);

        return $this->resultBuilder->build($variant, $tasks, $employees, $assignment);
    }

    private function strategyFor(Variant $variant): AssignmentStrategy
    {
        return match ($variant) {
            Variant::Cost => $this->minCost,
            Variant::Makespan => $this->minMakespan,
        };
    }

    /** @param  list<int>  $ids */
    private function assertUniqueIds(array $ids, string $kind): void
    {
        $duplicates = array_keys(array_filter(array_count_values($ids), fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            sort($duplicates);
            throw new InvalidArgumentException("Duplicate {$kind} ids: ".implode(', ', $duplicates).'.');
        }
    }
}

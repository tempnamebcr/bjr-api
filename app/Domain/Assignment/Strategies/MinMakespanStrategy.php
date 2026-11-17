<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Strategies;

use App\Domain\Assignment\Contracts\AssignmentStrategy;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use LogicException;

/**
 * Minimum makespan: a heuristic, because the problem (parallel machines with eligibility) is NP-hard.
 *
 * Construction is hierarchical LPT (Longest Processing Time first), O(T log T + T·E):
 * - Most constrained first (skill DESC): senior-only tasks are placed while seniors are still free,
 *   so flexible low-skill work can't crowd them out. Eligibility is nested, so the low-skill
 *   tasks placed last can always go to anyone.
 * - Longest first (estimation DESC): big tasks are hard to fit; small ones fill the gaps at the end.
 * - Each task goes to the least-loaded eligible employee (tie: cheaper rate, then lower id).
 */
final class MinMakespanStrategy implements AssignmentStrategy
{
    public function assign(array $tasks, array $employees): array
    {
        usort($tasks, fn (Task $a, Task $b): int => [$b->skillLevel, $b->estimation, $a->id] <=> [$a->skillLevel, $a->estimation, $b->id]);

        $loads = [];
        foreach ($employees as $employee) {
            $loads[$employee->id] = 0.0;
        }

        $assignment = [];
        foreach ($tasks as $task) {
            $best = $this->leastLoadedEligible($task, $employees, $loads);
            $assignment[$task->id] = $best->id;
            $loads[$best->id] += $task->estimation;
        }

        ksort($assignment);

        return $assignment;
    }

    /**
     * Linear scan; with ≤ 30 employees that's microseconds. Per-skill-tier heaps would make this
     * O(S·log E) but only pay off at thousands of employees.
     *
     * @param  list<Employee>  $employees
     * @param  array<int, float>  $loads
     */
    private function leastLoadedEligible(Task $task, array $employees, array $loads): Employee
    {
        $best = null;

        foreach ($employees as $employee) {
            if (! $employee->canPerform($task)) {
                continue;
            }

            // Lexicographic comparison: current load, then rate, then id.
            if ($best === null
                || [$loads[$employee->id], $employee->hourlyRate, $employee->id]
                    < [$loads[$best->id], $best->hourlyRate, $best->id]) {
                $best = $employee;
            }
        }

        return $best ?? throw new LogicException("Task {$task->id} has no eligible employee.");
    }
}

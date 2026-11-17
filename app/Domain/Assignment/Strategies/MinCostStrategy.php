<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Strategies;

use App\Domain\Assignment\Contracts\AssignmentStrategy;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use LogicException;

/**
 * Minimum total cost: exact, O(T log T + T·E).
 *
 * Total cost = Σ rate(e) × estimation(t). There is no capacity limit and stacking work on one
 * employee costs nothing extra, so the objective splits per task: giving every task to its
 * cheapest eligible employee is optimal. (Comparing rates is enough, since a task's estimation
 * is the same whoever does it.)
 *
 * Tie-break when several eligible employees share the cheapest rate: the least-loaded one, then
 * the lowest id. That doesn't change the cost but keeps the makespan from growing for nothing.
 * Tasks go longest-first, so this balancing behaves like LPT among the tied employees.
 */
final class MinCostStrategy implements AssignmentStrategy
{
    public function assign(array $tasks, array $employees): array
    {
        usort($tasks, fn (Task $a, Task $b): int => [$b->estimation, $a->id] <=> [$a->estimation, $b->id]);

        $loads = [];
        foreach ($employees as $employee) {
            $loads[$employee->id] = 0.0;
        }

        $assignment = [];
        foreach ($tasks as $task) {
            $best = $this->cheapestEligible($task, $employees, $loads);
            $assignment[$task->id] = $best->id;
            $loads[$best->id] += $task->estimation;
        }

        ksort($assignment);

        return $assignment;
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, float>  $loads
     */
    private function cheapestEligible(Task $task, array $employees, array $loads): Employee
    {
        $best = null;

        foreach ($employees as $employee) {
            if (! $employee->canPerform($task)) {
                continue;
            }

            // Lexicographic comparison: rate, then current load, then id.
            if ($best === null
                || [$employee->hourlyRate, $loads[$employee->id], $employee->id]
                    < [$best->hourlyRate, $loads[$best->id], $best->id]) {
                $best = $employee;
            }
        }

        return $best ?? throw new LogicException("Task {$task->id} has no eligible employee.");
    }
}

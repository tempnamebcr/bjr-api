<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;

/**
 * Deterministic local search on top of a constructed schedule (LPT can miss the optimum,
 * e.g. tasks 3,3,2,2,2 on two workers: LPT gives 7, one swap gives 6).
 *
 * Phase 1, makespan: take work off a critical employee (load == makespan).
 *   - MOVE one of its tasks to an eligible employee who stays below the makespan, else
 *   - SWAP one of its tasks with a shorter task of another employee (both eligible),
 *     where the other employee stays below the makespan.
 *   Every accepted step lowers the makespan or the number of employees at it, so it terminates.
 *   Stops early at the lower bound, which is proven optimal.
 *
 * Phase 2, cost: with the makespan fixed as a cap, move tasks to cheaper eligible employees who
 * have room, biggest saving first. Cost strictly drops each step, so it terminates. This is
 * greedy, not an exact min-cost at that makespan.
 *
 * Every candidate is compared on a full key ending in ids, so equal inputs give equal outputs.
 * Both phases are also capped by $maxRounds as a safety net.
 */
final readonly class MakespanImprover
{
    private const EPSILON = 1e-9;

    public function __construct(private int $maxRounds = 10_000) {}

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $assignment  taskId => employeeId (complete and eligible)
     * @return array<int, int> improved taskId => employeeId, sorted by taskId
     */
    public function improve(array $tasks, array $employees, array $assignment, float $lowerBound): array
    {
        $tasksById = [];
        foreach ($tasks as $task) {
            $tasksById[$task->id] = $task;
        }
        ksort($tasksById);

        $employeesById = [];
        foreach ($employees as $employee) {
            $employeesById[$employee->id] = $employee;
        }
        ksort($employeesById);

        if ($tasksById === [] || $employeesById === []) {
            return $assignment;
        }

        $loads = array_fill_keys(array_keys($employeesById), 0.0);
        foreach ($assignment as $taskId => $employeeId) {
            $loads[$employeeId] += $tasksById[$taskId]->estimation;
        }

        $this->reduceMakespan($tasksById, $employeesById, $assignment, $loads, $lowerBound);
        $this->reduceCost($tasksById, $employeesById, $assignment, $loads);

        ksort($assignment);

        return $assignment;
    }

    /**
     * @param  array<int, Task>  $tasksById
     * @param  array<int, Employee>  $employeesById
     * @param  array<int, int>  $assignment
     * @param  array<int, float>  $loads
     */
    private function reduceMakespan(array $tasksById, array $employeesById, array &$assignment, array &$loads, float $lowerBound): void
    {
        for ($round = 0; $round < $this->maxRounds; $round++) {
            $makespan = max($loads);

            if ($makespan <= $lowerBound + self::EPSILON) {
                return;
            }

            $improved = false;
            foreach ($employeesById as $employee) {
                if ($loads[$employee->id] < $makespan - self::EPSILON) {
                    continue;
                }

                if ($this->applyBestMove($employee, $makespan, $tasksById, $employeesById, $assignment, $loads)
                    || $this->applyBestSwap($employee, $makespan, $tasksById, $employeesById, $assignment, $loads)) {
                    $improved = true;
                    break;
                }
            }

            if (! $improved) {
                return;
            }
        }
    }

    /**
     * @param  array<int, Task>  $tasksById
     * @param  array<int, Employee>  $employeesById
     * @param  array<int, int>  $assignment
     * @param  array<int, float>  $loads
     */
    private function applyBestMove(Employee $critical, float $makespan, array $tasksById, array $employeesById, array &$assignment, array &$loads): bool
    {
        $best = null; // [resulting max of the two loads, taskId, targetEmployeeId]

        foreach ($tasksById as $task) {
            if ($assignment[$task->id] !== $critical->id) {
                continue;
            }

            foreach ($employeesById as $target) {
                if ($target->id === $critical->id || ! $target->canPerform($task)) {
                    continue;
                }

                $targetLoad = $loads[$target->id] + $task->estimation;
                if ($targetLoad >= $makespan - self::EPSILON) {
                    continue;
                }

                $key = [max($loads[$critical->id] - $task->estimation, $targetLoad), $task->id, $target->id];
                if ($best === null || $key < $best) {
                    $best = $key;
                }
            }
        }

        if ($best === null) {
            return false;
        }

        [, $taskId, $targetId] = $best;
        $this->move($tasksById[$taskId], $targetId, $assignment, $loads);

        return true;
    }

    /**
     * @param  array<int, Task>  $tasksById
     * @param  array<int, Employee>  $employeesById
     * @param  array<int, int>  $assignment
     * @param  array<int, float>  $loads
     */
    private function applyBestSwap(Employee $critical, float $makespan, array $tasksById, array $employeesById, array &$assignment, array &$loads): bool
    {
        $best = null; // [resulting max of the two loads, criticalTaskId, otherTaskId]

        foreach ($tasksById as $mine) {
            if ($assignment[$mine->id] !== $critical->id) {
                continue;
            }

            foreach ($tasksById as $theirs) {
                $other = $employeesById[$assignment[$theirs->id]];

                // Only swaps that shrink the critical employee's load, and only eligible ones.
                if ($other->id === $critical->id
                    || $mine->estimation <= $theirs->estimation + self::EPSILON
                    || ! $other->canPerform($mine)
                    || ! $critical->canPerform($theirs)) {
                    continue;
                }

                $delta = $mine->estimation - $theirs->estimation;
                $otherLoad = $loads[$other->id] + $delta;
                if ($otherLoad >= $makespan - self::EPSILON) {
                    continue;
                }

                $key = [max($loads[$critical->id] - $delta, $otherLoad), $mine->id, $theirs->id];
                if ($best === null || $key < $best) {
                    $best = $key;
                }
            }
        }

        if ($best === null) {
            return false;
        }

        [, $mineId, $theirsId] = $best;
        $otherId = $assignment[$theirsId];
        $this->move($tasksById[$mineId], $otherId, $assignment, $loads);
        $this->move($tasksById[$theirsId], $critical->id, $assignment, $loads);

        return true;
    }

    /**
     * @param  array<int, Task>  $tasksById
     * @param  array<int, Employee>  $employeesById
     * @param  array<int, int>  $assignment
     * @param  array<int, float>  $loads
     */
    private function reduceCost(array $tasksById, array $employeesById, array &$assignment, array &$loads): void
    {
        $cap = max($loads);

        for ($round = 0; $round < $this->maxRounds; $round++) {
            $best = null; // [-saving, taskId, targetEmployeeId]

            foreach ($tasksById as $task) {
                $current = $employeesById[$assignment[$task->id]];

                foreach ($employeesById as $target) {
                    if ($target->hourlyRate >= $current->hourlyRate - self::EPSILON
                        || ! $target->canPerform($task)
                        || $cap + self::EPSILON < $loads[$target->id] + $task->estimation) {
                        continue;
                    }

                    $saving = ($current->hourlyRate - $target->hourlyRate) * $task->estimation;
                    $key = [-$saving, $task->id, $target->id];
                    if ($best === null || $key < $best) {
                        $best = $key;
                    }
                }
            }

            if ($best === null) {
                return;
            }

            [, $taskId, $targetId] = $best;
            $this->move($tasksById[$taskId], $targetId, $assignment, $loads);
        }
    }

    /**
     * @param  array<int, int>  $assignment
     * @param  array<int, float>  $loads
     */
    private function move(Task $task, int $targetId, array &$assignment, array &$loads): void
    {
        $loads[$assignment[$task->id]] -= $task->estimation;
        $loads[$targetId] += $task->estimation;
        $assignment[$task->id] = $targetId;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use LogicException;

/**
 * A value no schedule can beat. If a schedule reaches it, that schedule is proven optimal.
 *
 *   LB = max( longest task,
 *             max over skill k: hours of tasks needing skill >= k / employees with skill >= k )
 *
 * - A task can't be split, so someone works at least the longest task.
 * - Tasks needing skill >= k can only go to employees with skill >= k (eligibility is nested),
 *   so the busiest of those works at least their average share.
 *
 * When every estimation is a whole number, every load is too, so the bound rounds up.
 */
final class MakespanLowerBound
{
    private const EPSILON = 1e-9;

    /**
     * Precondition: the instance is feasible.
     *
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    public function compute(array $tasks, array $employees): float
    {
        $bound = 0.0;
        $skillLevels = [];
        $allIntegral = true;

        foreach ($tasks as $task) {
            $bound = max($bound, $task->estimation);
            $skillLevels[$task->skillLevel] = true;
            $allIntegral = $allIntegral && floor($task->estimation) === $task->estimation;
        }

        foreach (array_keys($skillLevels) as $level) {
            $bound = max($bound, $this->averageLoadAtOrAbove($level, $tasks, $employees));
        }

        // Subtract epsilon first so float noise like 11.000000001 doesn't round up to 12.
        return $allIntegral ? ceil($bound - self::EPSILON) : $bound;
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    private function averageLoadAtOrAbove(int $level, array $tasks, array $employees): float
    {
        $hours = 0.0;
        foreach ($tasks as $task) {
            if ($task->skillLevel >= $level) {
                $hours += $task->estimation;
            }
        }

        $capable = 0;
        foreach ($employees as $employee) {
            if ($employee->skillLevel >= $level) {
                $capable++;
            }
        }

        if ($capable === 0) {
            throw new LogicException("No employee has skill level {$level} or higher.");
        }

        return $hours / $capable;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;

/**
 * An instance is feasible when every task has at least one eligible employee.
 *
 * Eligibility is nested (skill >= required), so "some employee can do it" is the same as
 * "the most skilled employee can do it". One max over employees replaces a T×E scan: O(T + E).
 */
final class FeasibilityChecker
{
    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @return list<int> ids of tasks nobody can perform, ascending (empty = feasible)
     */
    public function unassignableTaskIds(array $tasks, array $employees): array
    {
        $maxSkill = 0;
        foreach ($employees as $employee) {
            $maxSkill = max($maxSkill, $employee->skillLevel);
        }

        $ids = [];
        foreach ($tasks as $task) {
            if ($task->skillLevel > $maxSkill) {
                $ids[] = $task->id;
            }
        }

        // Sorted so the output doesn't depend on input order (determinism requirement).
        sort($ids);

        return $ids;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Contracts;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;

/**
 * One optimization variant: decides which employee performs each task.
 * Measuring the decision (price, makespan, timeline) is ResultBuilder's job, not the strategy's.
 */
interface AssignmentStrategy
{
    /**
     * Precondition: the instance is feasible (every task has an eligible employee).
     *
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @return array<int, int> taskId => employeeId
     */
    public function assign(array $tasks, array $employees): array;
}

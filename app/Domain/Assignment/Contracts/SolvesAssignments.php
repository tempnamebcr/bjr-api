<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Contracts;

use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;

/**
 * What callers (HTTP, CLI) depend on. Lets a decorator such as the Redis cache wrap the
 * real solver without the callers or the solver knowing.
 */
interface SolvesAssignments
{
    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    public function solve(array $tasks, array $employees, Variant $variant): AssignmentResult;
}

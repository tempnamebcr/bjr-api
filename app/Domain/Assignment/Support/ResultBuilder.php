<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Assignment;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\EmployeeLoad;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use LogicException;

/**
 * Turns a strategy's decision (taskId => employeeId) into the full result:
 * timeline, per-employee loads, total price and makespan.
 *
 * Keeping this out of the strategies means both variants are measured by exactly
 * the same code, so their numbers are directly comparable.
 */
final class ResultBuilder
{
    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $taskToEmployee  taskId => employeeId
     */
    public function build(Variant $variant, array $tasks, array $employees, array $taskToEmployee): AssignmentResult
    {
        $employeesById = [];
        foreach ($employees as $employee) {
            $employeesById[$employee->id] = $employee;
        }

        // Each employee works their tasks back-to-back from t = 0, in taskId order.
        // Order doesn't change loads or price; fixing it only makes the timeline deterministic.
        usort($tasks, fn (Task $a, Task $b): int => $a->id <=> $b->id);

        $loads = array_fill_keys(array_keys($employeesById), 0.0);
        $assignments = [];
        $totalPrice = 0.0;

        foreach ($tasks as $task) {
            $employee = $this->assignedEmployee($task, $taskToEmployee, $employeesById);

            $start = $loads[$employee->id];
            $end = $start + $task->estimation;

            $assignments[] = new Assignment($task->id, $employee->id, $start, $end);
            $loads[$employee->id] = $end;
            $totalPrice += $employee->hourlyRate * $task->estimation;
        }

        // Every employee is listed, idle ones with 0 h (the dashboard shows them muted).
        ksort($loads);
        $employeeLoads = [];
        foreach ($loads as $employeeId => $hours) {
            $employeeLoads[] = new EmployeeLoad($employeeId, $hours);
        }

        $makespan = $loads === [] ? 0.0 : max($loads);

        return AssignmentResult::feasible($variant, $assignments, $totalPrice, $makespan, $employeeLoads);
    }

    /**
     * Guards against strategy bugs: a missing, unknown or ineligible assignment is a programming error,
     * not a user error, so it fails loudly instead of producing a wrong schedule.
     *
     * @param  array<int, int>  $taskToEmployee
     * @param  array<int, Employee>  $employeesById
     */
    private function assignedEmployee(Task $task, array $taskToEmployee, array $employeesById): Employee
    {
        $employeeId = $taskToEmployee[$task->id]
            ?? throw new LogicException("Task {$task->id} was not assigned.");

        $employee = $employeesById[$employeeId]
            ?? throw new LogicException("Task {$task->id} was assigned to unknown employee {$employeeId}.");

        if (! $employee->canPerform($task)) {
            throw new LogicException("Task {$task->id} was assigned to ineligible employee {$employeeId}.");
        }

        return $employee;
    }
}

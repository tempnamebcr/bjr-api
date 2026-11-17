<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Support\FeasibilityChecker;
use PHPUnit\Framework\TestCase;

final class FeasibilityCheckerTest extends TestCase
{
    private FeasibilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FeasibilityChecker;
    }

    public function test_it_returns_no_ids_when_every_task_has_an_eligible_employee(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 3, 300)];
        $tasks = [new Task(1, 1, 2), new Task(2, 2, 1), new Task(3, 3, 4)];

        $this->assertSame([], $this->checker->unassignableTaskIds($tasks, $employees));
    }

    public function test_a_task_requiring_exactly_the_max_skill_is_assignable(): void
    {
        $employees = [new Employee(1, 2, 100)];
        $tasks = [new Task(1, 2, 1)];

        $this->assertSame([], $this->checker->unassignableTaskIds($tasks, $employees));
    }

    public function test_it_reports_tasks_above_the_highest_employee_skill(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 400)];
        $tasks = [new Task(1, 1, 2), new Task(2, 3, 1), new Task(3, 2, 1)];

        $this->assertSame([2], $this->checker->unassignableTaskIds($tasks, $employees));
    }

    public function test_unassignable_ids_are_sorted_regardless_of_input_order(): void
    {
        $employees = [new Employee(1, 1, 100)];
        $tasks = [new Task(9, 3, 1), new Task(4, 2, 1), new Task(7, 1, 1), new Task(2, 2, 1)];

        $this->assertSame([2, 4, 9], $this->checker->unassignableTaskIds($tasks, $employees));
    }

    public function test_without_employees_every_task_is_unassignable(): void
    {
        $tasks = [new Task(2, 1, 1), new Task(1, 1, 1)];

        $this->assertSame([1, 2], $this->checker->unassignableTaskIds($tasks, []));
    }

    public function test_no_tasks_is_trivially_feasible(): void
    {
        $this->assertSame([], $this->checker->unassignableTaskIds([], [new Employee(1, 1, 100)]));
    }
}

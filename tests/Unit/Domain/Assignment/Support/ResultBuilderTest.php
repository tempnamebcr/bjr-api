<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Assignment;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\EmployeeLoad;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Domain\Assignment\Support\ResultBuilder;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ResultBuilderTest extends TestCase
{
    private ResultBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ResultBuilder;
    }

    public function test_it_computes_price_loads_and_makespan(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 400)];
        $tasks = [new Task(1, 1, 2), new Task(2, 2, 3), new Task(3, 1, 1)];

        $result = $this->builder->build(Variant::Cost, $tasks, $employees, [1 => 1, 2 => 2, 3 => 1]);

        // 2h*100 + 3h*400 + 1h*100
        $this->assertSame(1500.0, $result->totalPrice);
        $this->assertSame(3.0, $result->makespan);
        $this->assertEquals(
            [new EmployeeLoad(1, 3), new EmployeeLoad(2, 3)],
            $result->employeeLoads,
        );
        $this->assertTrue($result->isFeasible());
        $this->assertSame(Variant::Cost, $result->variant);
        // max(longest task 3, skill≥2: 3h/1, skill≥1: 6h/2) = 3 → this schedule is optimal.
        $this->assertSame(3.0, $result->lowerBound);
        $this->assertTrue($result->isProvenOptimalMakespan());
    }

    public function test_an_employees_tasks_run_back_to_back_in_task_id_order(): void
    {
        $employees = [new Employee(1, 1, 100)];
        $tasks = [new Task(3, 1, 1), new Task(1, 1, 2), new Task(2, 1, 4)];

        $result = $this->builder->build(Variant::Makespan, $tasks, $employees, [1 => 1, 2 => 1, 3 => 1]);

        $this->assertEquals(
            [new Assignment(1, 1, 0, 2), new Assignment(2, 1, 2, 6), new Assignment(3, 1, 6, 7)],
            $result->assignments,
        );
        $this->assertSame(7.0, $result->makespan);
    }

    public function test_idle_employees_are_listed_with_zero_hours_sorted_by_id(): void
    {
        $employees = [new Employee(5, 1, 100), new Employee(2, 1, 100), new Employee(9, 1, 100)];
        $tasks = [new Task(1, 1, 4)];

        $result = $this->builder->build(Variant::Cost, $tasks, $employees, [1 => 2]);

        $this->assertEquals(
            [new EmployeeLoad(2, 4), new EmployeeLoad(5, 0), new EmployeeLoad(9, 0)],
            $result->employeeLoads,
        );
    }

    public function test_fractional_hours_are_supported(): void
    {
        $employees = [new Employee(1, 1, 80)];
        $tasks = [new Task(1, 1, 1.5), new Task(2, 1, 0.25)];

        $result = $this->builder->build(Variant::Cost, $tasks, $employees, [1 => 1, 2 => 1]);

        $this->assertSame(140.0, $result->totalPrice);
        $this->assertSame(1.75, $result->makespan);
    }

    public function test_no_tasks_gives_zero_price_and_makespan(): void
    {
        $result = $this->builder->build(Variant::Cost, [], [new Employee(1, 1, 100)], []);

        $this->assertSame(0.0, $result->totalPrice);
        $this->assertSame(0.0, $result->makespan);
        $this->assertSame([], $result->assignments);
    }

    public function test_it_rejects_an_unassigned_task(): void
    {
        $this->expectExceptionObject(new LogicException('Task 2 was not assigned.'));

        $this->builder->build(Variant::Cost, [new Task(1, 1, 1), new Task(2, 1, 1)], [new Employee(1, 1, 100)], [1 => 1]);
    }

    public function test_it_rejects_an_unknown_employee(): void
    {
        $this->expectExceptionObject(new LogicException('Task 1 was assigned to unknown employee 7.'));

        $this->builder->build(Variant::Cost, [new Task(1, 1, 1)], [new Employee(1, 1, 100)], [1 => 7]);
    }

    public function test_it_rejects_an_ineligible_employee(): void
    {
        $this->expectExceptionObject(new LogicException('Task 1 was assigned to ineligible employee 1.'));

        $this->builder->build(Variant::Cost, [new Task(1, 2, 1)], [new Employee(1, 1, 100)], [1 => 1]);
    }
}

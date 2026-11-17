<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\ResultStatus;
use App\Domain\Assignment\Enums\Variant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AssignmentSolverTest extends TestCase
{
    private AssignmentSolver $solver;

    protected function setUp(): void
    {
        $this->solver = new AssignmentSolver;
    }

    public function test_each_variant_runs_its_own_strategy(): void
    {
        // A cheap junior and an expensive senior, two junior tasks.
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 1000)];
        $tasks = [new Task(1, 1, 2), new Task(2, 1, 2)];

        $cost = $this->solver->solve($tasks, $employees, Variant::Cost);
        $makespan = $this->solver->solve($tasks, $employees, Variant::Makespan);

        $this->assertSame(Variant::Cost, $cost->variant);
        $this->assertSame(400.0, $cost->totalPrice);   // both on the junior
        $this->assertSame(4.0, $cost->makespan);

        $this->assertSame(Variant::Makespan, $makespan->variant);
        $this->assertSame(2.0, $makespan->makespan);  // split across both
        $this->assertSame(2200.0, $makespan->totalPrice);
    }

    public function test_an_instance_with_an_unassignable_task_is_infeasible_for_every_variant(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 200)];
        $tasks = [new Task(1, 1, 1), new Task(2, 3, 1), new Task(3, 3, 2)];

        foreach (Variant::cases() as $variant) {
            $result = $this->solver->solve($tasks, $employees, $variant);

            $this->assertSame(ResultStatus::Infeasible, $result->status);
            $this->assertSame([2, 3], $result->unassignableTaskIds);
            $this->assertSame([], $result->assignments);
        }
    }

    public function test_it_rejects_duplicate_task_ids(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Duplicate task ids: 1.'));

        $this->solver->solve([new Task(1, 1, 1), new Task(1, 1, 2)], [new Employee(1, 1, 100)], Variant::Cost);
    }

    public function test_it_rejects_duplicate_employee_ids(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Duplicate employee ids: 3.'));

        $this->solver->solve([new Task(1, 1, 1)], [new Employee(3, 1, 100), new Employee(3, 2, 200)], Variant::Cost);
    }

    public function test_no_tasks_is_feasible_with_zero_totals(): void
    {
        $result = $this->solver->solve([], [new Employee(1, 1, 100)], Variant::Makespan);

        $this->assertTrue($result->isFeasible());
        $this->assertSame(0.0, $result->totalPrice);
        $this->assertSame(0.0, $result->makespan);
    }
}

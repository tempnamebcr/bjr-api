<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Strategies;

use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Domain\Assignment\Strategies\MinMakespanStrategy;
use App\Domain\Assignment\Support\MakespanLowerBound;
use App\Domain\Assignment\Support\ResultBuilder;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MinMakespanStrategyTest extends TestCase
{
    private MinMakespanStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new MinMakespanStrategy;
    }

    public function test_senior_tasks_are_placed_before_flexible_work(): void
    {
        // Plain LPT would put the 3h junior task on the senior first (tie on load, lower id),
        // then stack both senior tasks there too: makespan 7. Skill-first gives 4.
        $employees = [new Employee(1, 2, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 3), new Task(2, 2, 2), new Task(3, 2, 2)];

        $assignment = $this->strategy->assign($tasks, $employees);

        $this->assertSame([1 => 2, 2 => 1, 3 => 1], $assignment);
        $this->assertSame(4.0, $this->solve($tasks, $employees)->makespan);
    }

    public function test_equal_load_prefers_the_cheaper_employee_then_lower_id(): void
    {
        $employees = [new Employee(1, 1, 500), new Employee(2, 1, 100), new Employee(3, 1, 100)];

        $this->assertSame([1 => 2], $this->strategy->assign([new Task(1, 1, 1)], $employees));
    }

    public function test_balanced_work_reaches_the_lower_bound(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 2), new Task(2, 1, 2), new Task(3, 1, 2), new Task(4, 1, 2)];

        $this->assertSame(4.0, $this->solve($tasks, $employees)->makespan);
        $this->assertSame(4.0, (new MakespanLowerBound)->compute($tasks, $employees));
    }

    public function test_highly_skewed_durations_finish_with_the_longest_task(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100), new Employee(3, 1, 100)];
        $tasks = [new Task(1, 1, 100)];
        for ($id = 2; $id <= 51; $id++) {
            $tasks[] = new Task($id, 1, 1);
        }

        // The 100h task goes first; the 50 small ones spread over the other two employees.
        $result = $this->solve($tasks, $employees);

        $this->assertSame(100.0, $result->makespan);
        $this->assertSame(100.0, (new MakespanLowerBound)->compute($tasks, $employees));
    }

    public function test_output_does_not_depend_on_input_order(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 150), new Employee(3, 3, 200)];
        $tasks = [new Task(1, 1, 3), new Task(2, 2, 3), new Task(3, 3, 2), new Task(4, 2, 1), new Task(5, 1, 4)];

        $expected = $this->strategy->assign($tasks, $employees);

        $this->assertSame($expected, $this->strategy->assign(array_reverse($tasks), array_reverse($employees)));
    }

    public function test_it_rejects_a_task_without_an_eligible_employee(): void
    {
        $this->expectException(LogicException::class);

        $this->strategy->assign([new Task(1, 3, 1)], [new Employee(1, 1, 100)]);
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    private function solve(array $tasks, array $employees): AssignmentResult
    {
        return (new ResultBuilder)->build(Variant::Makespan, $tasks, $employees, $this->strategy->assign($tasks, $employees));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Support;

use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Domain\Assignment\Support\MakespanImprover;
use App\Domain\Assignment\Support\MakespanLowerBound;
use App\Domain\Assignment\Support\ResultBuilder;
use PHPUnit\Framework\TestCase;

final class MakespanImproverTest extends TestCase
{
    public function test_a_swap_fixes_the_classic_lpt_miss(): void
    {
        // Tasks 3,3,2,2,2 on two workers. LPT gives e1 = {3,2,2} = 7, e2 = {3,2} = 5.
        // No single move helps; swapping a 3 (e1) with a 2 (e2) gives 6 / 6.
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 3), new Task(2, 1, 3), new Task(3, 1, 2), new Task(4, 1, 2), new Task(5, 1, 2)];
        $lpt = [1 => 1, 2 => 2, 3 => 1, 4 => 2, 5 => 1];

        $result = $this->improveAndBuild($tasks, $employees, $lpt);

        $this->assertSame(7.0, $this->build($tasks, $employees, $lpt)->makespan);
        $this->assertSame(6.0, $result->makespan);
    }

    public function test_moves_rebalance_an_overloaded_employee(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 2), new Task(2, 1, 2), new Task(3, 1, 2), new Task(4, 1, 2)];

        $result = $this->improveAndBuild($tasks, $employees, [1 => 1, 2 => 1, 3 => 1, 4 => 1]);

        $this->assertSame(4.0, $result->makespan);
    }

    public function test_work_only_moves_to_eligible_employees(): void
    {
        // The junior (e2) is idle, but only the second senior (e3) may take a senior task.
        $employees = [new Employee(1, 2, 100), new Employee(2, 1, 100), new Employee(3, 2, 100)];
        $tasks = [new Task(1, 2, 4), new Task(2, 2, 4)];

        $improved = $this->improver()->improve($tasks, $employees, [1 => 1, 2 => 1], $this->lowerBound($tasks, $employees));

        // Both tasks are 4h; the tie-break moves the lower task id. The junior gets nothing.
        $this->assertSame([1 => 3, 2 => 1], $improved);
    }

    public function test_the_cost_pass_moves_work_to_cheaper_employees_without_raising_the_makespan(): void
    {
        // e1:4h, e2:2h and the expensive e3:2h → makespan 4, price 2400.
        // e2 has 2h of room under the makespan, so both of e3's 1h tasks move there.
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100), new Employee(3, 1, 900)];
        $tasks = [new Task(1, 1, 4), new Task(2, 1, 2), new Task(3, 1, 1), new Task(4, 1, 1)];
        $assignment = [1 => 1, 2 => 2, 3 => 3, 4 => 3];

        $before = $this->build($tasks, $employees, $assignment);
        $after = $this->improveAndBuild($tasks, $employees, $assignment);

        $this->assertSame(2400.0, $before->totalPrice);
        $this->assertSame(800.0, $after->totalPrice);
        $this->assertSame(4.0, $after->makespan);
    }

    public function test_the_cost_pass_does_not_exceed_the_makespan(): void
    {
        // The cheap employee is full (4h = makespan), so the expensive one keeps its task.
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 900)];
        $tasks = [new Task(1, 1, 4), new Task(2, 1, 1)];

        $this->assertSame([1 => 1, 2 => 2], $this->improver()->improve($tasks, $employees, [1 => 1, 2 => 2], 4));
    }

    public function test_a_zero_round_limit_leaves_the_assignment_unchanged(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 2), new Task(2, 1, 2)];

        $this->assertSame([1 => 1, 2 => 1], (new MakespanImprover(maxRounds: 0))->improve($tasks, $employees, [1 => 1, 2 => 1], 2));
    }

    public function test_no_tasks_returns_an_empty_assignment(): void
    {
        $this->assertSame([], $this->improver()->improve([], [new Employee(1, 1, 100)], [], 0));
    }

    private function improver(): MakespanImprover
    {
        return new MakespanImprover;
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    private function lowerBound(array $tasks, array $employees): float
    {
        return (new MakespanLowerBound)->compute($tasks, $employees);
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $assignment
     */
    private function improveAndBuild(array $tasks, array $employees, array $assignment): AssignmentResult
    {
        $improved = $this->improver()->improve($tasks, $employees, $assignment, $this->lowerBound($tasks, $employees));

        return $this->build($tasks, $employees, $improved);
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $assignment
     */
    private function build(array $tasks, array $employees, array $assignment): AssignmentResult
    {
        return (new ResultBuilder)->build(Variant::Makespan, $tasks, $employees, $assignment);
    }
}

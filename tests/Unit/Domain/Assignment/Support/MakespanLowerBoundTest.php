<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Support\MakespanLowerBound;
use PHPUnit\Framework\TestCase;

final class MakespanLowerBoundTest extends TestCase
{
    private MakespanLowerBound $bound;

    protected function setUp(): void
    {
        $this->bound = new MakespanLowerBound;
    }

    public function test_the_longest_task_dominates_when_durations_are_skewed(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100), new Employee(3, 1, 100)];
        $tasks = [new Task(1, 1, 100), new Task(2, 1, 1), new Task(3, 1, 1)];

        $this->assertSame(100.0, $this->bound->compute($tasks, $employees));
    }

    public function test_the_average_load_bounds_balanced_work(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 3), new Task(2, 1, 3), new Task(3, 1, 2), new Task(4, 1, 2), new Task(5, 1, 2)];

        $this->assertSame(6.0, $this->bound->compute($tasks, $employees));
    }

    public function test_a_scarce_skill_tier_raises_the_bound(): void
    {
        // Overall average is 12h / 4 = 3, but the 10h of senior work fits on only 2 seniors → 5.
        $employees = [
            new Employee(1, 1, 100), new Employee(2, 1, 100),
            new Employee(3, 2, 300), new Employee(4, 2, 300),
        ];
        $tasks = [new Task(1, 2, 5), new Task(2, 2, 5), new Task(3, 1, 1), new Task(4, 1, 1)];

        $this->assertSame(5.0, $this->bound->compute($tasks, $employees));
    }

    public function test_it_rounds_up_when_all_durations_are_whole_hours(): void
    {
        // 5h over 2 employees = 2.5, but whole-hour loads can't be 2.5 → 3.
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 2), new Task(2, 1, 2), new Task(3, 1, 1)];

        $this->assertSame(3.0, $this->bound->compute($tasks, $employees));
    }

    public function test_it_does_not_round_fractional_durations(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 1.5), new Task(2, 1, 1.5), new Task(3, 1, 1.5)];

        $this->assertSame(2.25, $this->bound->compute($tasks, $employees));
    }

    public function test_no_tasks_gives_zero(): void
    {
        $this->assertSame(0.0, $this->bound->compute([], [new Employee(1, 1, 100)]));
    }
}

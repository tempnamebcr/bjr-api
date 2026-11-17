<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Strategies;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Domain\Assignment\Strategies\MinCostStrategy;
use App\Domain\Assignment\Support\ResultBuilder;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MinCostStrategyTest extends TestCase
{
    private MinCostStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new MinCostStrategy;
    }

    public function test_each_task_goes_to_its_cheapest_eligible_employee(): void
    {
        $employees = [
            new Employee(1, 1, 80),   // cheapest overall, but only skill 1
            new Employee(2, 2, 350),
            new Employee(3, 3, 300),  // senior AND cheaper than employee 2
            new Employee(4, 3, 700),
        ];
        $tasks = [new Task(1, 1, 2), new Task(2, 2, 3), new Task(3, 3, 1)];

        $this->assertSame([1 => 1, 2 => 3, 3 => 3], $this->strategy->assign($tasks, $employees));
    }

    public function test_widely_varying_rates_put_all_eligible_work_on_the_cheap_employee(): void
    {
        $employees = [new Employee(1, 1, 1), new Employee(2, 1, 10_000)];
        $tasks = [new Task(1, 1, 8), new Task(2, 1, 8), new Task(3, 1, 8)];

        $result = (new ResultBuilder)->build(
            Variant::Cost, $tasks, $employees, $this->strategy->assign($tasks, $employees),
        );

        // Cost ignores makespan: one employee works 24 h while the other idles.
        $this->assertSame(24.0, $result->totalPrice);
        $this->assertSame(24.0, $result->makespan);
    }

    public function test_equal_rates_are_balanced_longest_task_first(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 1, 100)];
        $tasks = [new Task(1, 1, 4), new Task(2, 1, 3), new Task(3, 1, 2), new Task(4, 1, 1)];

        // 4h→e1, 3h→e2, 2h→e2 (3<4), 1h→e1 (4<5): both end at 5 h, same cost as any other split.
        $this->assertSame([1 => 1, 2 => 2, 3 => 2, 4 => 1], $this->strategy->assign($tasks, $employees));
    }

    public function test_equal_rate_and_load_falls_back_to_lowest_employee_id(): void
    {
        $employees = [new Employee(7, 1, 100), new Employee(3, 1, 100)];

        $this->assertSame([1 => 3], $this->strategy->assign([new Task(1, 1, 1)], $employees));
    }

    public function test_output_does_not_depend_on_input_order(): void
    {
        $employees = [new Employee(1, 1, 100), new Employee(2, 2, 100), new Employee(3, 2, 200)];
        $tasks = [new Task(1, 1, 3), new Task(2, 2, 3), new Task(3, 1, 2), new Task(4, 2, 1)];

        $expected = $this->strategy->assign($tasks, $employees);

        $this->assertSame($expected, $this->strategy->assign(array_reverse($tasks), array_reverse($employees)));
    }

    public function test_it_rejects_a_task_without_an_eligible_employee(): void
    {
        $this->expectException(LogicException::class);

        $this->strategy->assign([new Task(1, 3, 1)], [new Employee(1, 1, 100)]);
    }
}

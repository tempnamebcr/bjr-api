<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Data;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmployeeTest extends TestCase
{
    public function test_it_maps_the_input_json_shape(): void
    {
        $employee = Employee::fromArray(['employeeId' => 4, 'skillLevel' => 3, 'hourlyRate' => 300]);

        $this->assertSame(4, $employee->id);
        $this->assertSame(3, $employee->skillLevel);
        $this->assertSame(300.0, $employee->hourlyRate);
    }

    public function test_it_can_perform_tasks_at_or_below_its_skill_level(): void
    {
        $employee = new Employee(1, 2, 100);

        $this->assertTrue($employee->canPerform(new Task(1, 1, 1)));
        $this->assertTrue($employee->canPerform(new Task(2, 2, 1)));
        $this->assertFalse($employee->canPerform(new Task(3, 3, 1)));
    }

    public function test_it_rejects_a_skill_level_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Employee(1, 0, 100);
    }

    public function test_it_rejects_a_negative_hourly_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Employee(1, 1, -1);
    }

    public function test_it_allows_a_zero_hourly_rate(): void
    {
        $this->assertSame(0.0, (new Employee(1, 1, 0))->hourlyRate);
    }
}

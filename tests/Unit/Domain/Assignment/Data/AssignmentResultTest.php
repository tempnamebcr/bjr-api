<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Data;

use App\Domain\Assignment\Data\Assignment;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\EmployeeLoad;
use App\Domain\Assignment\Enums\ResultStatus;
use App\Domain\Assignment\Enums\Variant;
use PHPUnit\Framework\TestCase;

final class AssignmentResultTest extends TestCase
{
    public function test_a_feasible_result_carries_assignments_and_totals(): void
    {
        $assignments = [new Assignment(taskId: 1, employeeId: 8, start: 0, end: 2)];
        $loads = [new EmployeeLoad(employeeId: 8, totalAssignedHours: 2)];

        $result = AssignmentResult::feasible(Variant::Cost, $assignments, totalPrice: 160, makespan: 2, employeeLoads: $loads, lowerBound: 2);

        $this->assertTrue($result->isFeasible());
        $this->assertSame(ResultStatus::Feasible, $result->status);
        $this->assertSame(Variant::Cost, $result->variant);
        $this->assertSame($assignments, $result->assignments);
        $this->assertSame(160.0, $result->totalPrice);
        $this->assertSame(2.0, $result->makespan);
        $this->assertSame($loads, $result->employeeLoads);
        $this->assertSame([], $result->unassignableTaskIds);
        $this->assertSame(2.0, $result->lowerBound);
        $this->assertTrue($result->isProvenOptimalMakespan());
    }

    public function test_a_makespan_above_the_lower_bound_is_not_proven_optimal(): void
    {
        $result = AssignmentResult::feasible(Variant::Cost, [], totalPrice: 0, makespan: 51, employeeLoads: [], lowerBound: 11);

        $this->assertFalse($result->isProvenOptimalMakespan());
    }

    public function test_an_infeasible_result_has_no_assignments_and_null_totals(): void
    {
        $result = AssignmentResult::infeasible(Variant::Makespan, [5, 9]);

        $this->assertFalse($result->isFeasible());
        $this->assertSame(ResultStatus::Infeasible, $result->status);
        $this->assertSame([], $result->assignments);
        $this->assertNull($result->totalPrice);
        $this->assertNull($result->makespan);
        $this->assertSame([], $result->employeeLoads);
        $this->assertSame([5, 9], $result->unassignableTaskIds);
        $this->assertNull($result->lowerBound);
        $this->assertFalse($result->isProvenOptimalMakespan());
    }
}

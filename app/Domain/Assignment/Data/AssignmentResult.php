<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Data;

use App\Domain\Assignment\Enums\ResultStatus;
use App\Domain\Assignment\Enums\Variant;

/**
 * Outcome of one optimization variant. Built only through the named constructors,
 * so a result is always either fully feasible (every task assigned, totals set)
 * or infeasible (no assignments, totals null, unassignable task ids listed).
 */
final readonly class AssignmentResult
{
    /**
     * @param  list<Assignment>  $assignments
     * @param  list<EmployeeLoad>  $employeeLoads
     * @param  list<int>  $unassignableTaskIds
     */
    private function __construct(
        public Variant $variant,
        public ResultStatus $status,
        public array $assignments,
        public ?float $totalPrice,
        public ?float $makespan,
        public array $employeeLoads,
        public array $unassignableTaskIds,
    ) {}

    /**
     * @param  list<Assignment>  $assignments
     * @param  list<EmployeeLoad>  $employeeLoads
     */
    public static function feasible(
        Variant $variant,
        array $assignments,
        float $totalPrice,
        float $makespan,
        array $employeeLoads,
    ): self {
        return new self($variant, ResultStatus::Feasible, $assignments, $totalPrice, $makespan, $employeeLoads, []);
    }

    /** @param  list<int>  $unassignableTaskIds */
    public static function infeasible(Variant $variant, array $unassignableTaskIds): self
    {
        return new self($variant, ResultStatus::Infeasible, [], null, null, [], $unassignableTaskIds);
    }

    public function isFeasible(): bool
    {
        return $this->status === ResultStatus::Feasible;
    }
}

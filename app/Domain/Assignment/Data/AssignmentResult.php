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
    /** Float tolerance, same value as the solver classes use. */
    private const EPSILON = 1e-9;

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
        public ?float $lowerBound,
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
        float $lowerBound,
    ): self {
        return new self($variant, ResultStatus::Feasible, $assignments, $totalPrice, $makespan, $employeeLoads, [], $lowerBound);
    }

    /** @param  list<int>  $unassignableTaskIds */
    public static function infeasible(Variant $variant, array $unassignableTaskIds): self
    {
        return new self($variant, ResultStatus::Infeasible, [], null, null, [], $unassignableTaskIds, null);
    }

    public function isFeasible(): bool
    {
        return $this->status === ResultStatus::Feasible;
    }

    /** True when the makespan reaches the lower bound: no schedule can finish earlier. */
    public function isProvenOptimalMakespan(): bool
    {
        return $this->makespan !== null
            && $this->lowerBound !== null
            && $this->makespan <= $this->lowerBound + self::EPSILON;
    }
}

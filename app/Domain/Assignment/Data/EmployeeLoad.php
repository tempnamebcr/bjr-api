<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Data;

final readonly class EmployeeLoad
{
    public function __construct(
        public int $employeeId,
        public float $totalAssignedHours,
    ) {}
}

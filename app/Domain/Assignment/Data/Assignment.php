<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Data;

/**
 * One task placed on one employee's timeline. start/end are hours from t = 0;
 * an employee's tasks run back-to-back, so end = start + estimation.
 */
final readonly class Assignment
{
    public function __construct(
        public int $taskId,
        public int $employeeId,
        public float $start,
        public float $end,
    ) {}
}

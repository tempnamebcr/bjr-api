<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Data;

use InvalidArgumentException;

final readonly class Employee
{
    public function __construct(
        public int $id,
        public int $skillLevel,
        public float $hourlyRate,
    ) {
        if ($skillLevel < 1) {
            throw new InvalidArgumentException("Employee {$id}: skillLevel must be at least 1.");
        }

        if ($hourlyRate < 0) {
            throw new InvalidArgumentException("Employee {$id}: hourlyRate must not be negative.");
        }
    }

    /**
     * Maps the input JSON shape ({employeeId, skillLevel, hourlyRate}) to the domain object.
     * Shape validation is the caller's job (HTTP request / CLI); this only guards domain rules.
     *
     * @param  array{employeeId: int, skillLevel: int, hourlyRate: int|float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['employeeId'], $data['skillLevel'], $data['hourlyRate']);
    }

    /** Eligibility rule from the brief: e.skillLevel >= t.skillLevel. */
    public function canPerform(Task $task): bool
    {
        return $this->skillLevel >= $task->skillLevel;
    }
}

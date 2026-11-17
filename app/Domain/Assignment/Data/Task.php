<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Data;

use InvalidArgumentException;

final readonly class Task
{
    public function __construct(
        public int $id,
        public int $skillLevel,
        public float $estimation,
    ) {
        if ($skillLevel < 1) {
            throw new InvalidArgumentException("Task {$id}: skillLevel must be at least 1.");
        }

        if ($estimation <= 0) {
            throw new InvalidArgumentException("Task {$id}: estimation must be greater than 0.");
        }
    }

    /**
     * Maps the input JSON shape ({taskId, skillLevel, estimation}) to the domain object.
     *
     * @param  array{taskId: int, skillLevel: int, estimation: int|float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['taskId'], $data['skillLevel'], $data['estimation']);
    }
}

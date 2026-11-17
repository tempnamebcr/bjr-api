<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Data;

use App\Domain\Assignment\Data\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function test_it_accepts_fractional_estimations(): void
    {
        $this->assertSame(1.5, (new Task(1, 1, 1.5))->estimation);
    }

    public function test_it_rejects_a_skill_level_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Task(1, 0, 1);
    }

    public function test_it_rejects_a_zero_estimation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Task(1, 1, 0);
    }
}

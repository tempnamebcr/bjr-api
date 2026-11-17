<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Assignment\Data;

use App\Domain\Assignment\Data\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function test_it_maps_the_input_json_shape(): void
    {
        $task = Task::fromArray(['taskId' => 13, 'skillLevel' => 3, 'estimation' => 5]);

        $this->assertSame(13, $task->id);
        $this->assertSame(3, $task->skillLevel);
        $this->assertSame(5.0, $task->estimation);
    }

    public function test_it_accepts_fractional_estimations(): void
    {
        $this->assertSame(1.5, Task::fromArray(['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1.5])->estimation);
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

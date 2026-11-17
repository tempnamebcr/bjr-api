<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Support\AssignmentInput;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Needs the framework (Validator facade), so it lives with the feature tests. */
final class AssignmentInputTest extends TestCase
{
    public function test_valid_input_maps_to_domain_objects(): void
    {
        $input = AssignmentInput::validate([
            'employees' => [['employeeId' => 1, 'skillLevel' => 2, 'hourlyRate' => 99.5]],
            'tasks' => [['taskId' => 7, 'skillLevel' => 1, 'estimation' => 1.5]],
        ]);

        $this->assertEquals([new Employee(1, 2, 99.5)], $input->employees);
        $this->assertEquals([new Task(7, 1, 1.5)], $input->tasks);
    }

    public function test_numeric_strings_are_cast_for_the_strictly_typed_domain(): void
    {
        $input = AssignmentInput::validate([
            'employees' => [['employeeId' => '1', 'skillLevel' => '2', 'hourlyRate' => '100']],
            'tasks' => [['taskId' => '3', 'skillLevel' => '1', 'estimation' => '2']],
        ]);

        $this->assertEquals([new Employee(1, 2, 100)], $input->employees);
        $this->assertEquals([new Task(3, 1, 2)], $input->tasks);
    }

    public function test_empty_lists_are_valid(): void
    {
        $input = AssignmentInput::validate(['employees' => [], 'tasks' => []]);

        $this->assertSame([], $input->employees);
        $this->assertSame([], $input->tasks);
    }

    public function test_input_at_the_size_limits_is_accepted_and_above_is_rejected(): void
    {
        config(['assignment.max_tasks' => 2, 'assignment.max_employees' => 1]);
        $employee = fn (int $id): array => ['employeeId' => $id, 'skillLevel' => 1, 'hourlyRate' => 100];
        $task = fn (int $id): array => ['taskId' => $id, 'skillLevel' => 1, 'estimation' => 1];

        $atLimit = AssignmentInput::validate(['employees' => [$employee(1)], 'tasks' => [$task(1), $task(2)]]);
        $this->assertCount(2, $atLimit->tasks);

        try {
            AssignmentInput::validate(['employees' => [$employee(1), $employee(2)], 'tasks' => [$task(1), $task(2), $task(3)]]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('employees', $e->errors());
            $this->assertArrayHasKey('tasks', $e->errors());
        }
    }

    public function test_the_default_limits_cover_the_brief_and_bound_worst_case_time(): void
    {
        $this->assertSame(1000, config('assignment.max_tasks'));
        $this->assertSame(100, config('assignment.max_employees'));
    }

    /** @return array<string, array{array<mixed>, string}> */
    public static function invalidInputs(): array
    {
        $employee = ['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100];
        $task = ['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1];

        return [
            'missing employees' => [['tasks' => [$task]], 'employees'],
            'employees is an object' => [['employees' => ['a' => $employee], 'tasks' => [$task]], 'employees'],
            'duplicate employee id' => [['employees' => [$employee, $employee], 'tasks' => [$task]], 'employees.0.employeeId'],
            'skill level 0' => [['employees' => [[...$employee, 'skillLevel' => 0]], 'tasks' => [$task]], 'employees.0.skillLevel'],
            'negative rate' => [['employees' => [[...$employee, 'hourlyRate' => -1]], 'tasks' => [$task]], 'employees.0.hourlyRate'],
            'unknown employee field' => [['employees' => [[...$employee, 'name' => 'x']], 'tasks' => [$task]], 'employees.0'],
            'missing task field' => [['employees' => [$employee], 'tasks' => [['taskId' => 1, 'skillLevel' => 1]]], 'tasks.0.estimation'],
            'zero estimation' => [['employees' => [$employee], 'tasks' => [[...$task, 'estimation' => 0]]], 'tasks.0.estimation'],
            'non-numeric estimation' => [['employees' => [$employee], 'tasks' => [[...$task, 'estimation' => 'two']]], 'tasks.0.estimation'],
            'fractional task id' => [['employees' => [$employee], 'tasks' => [[...$task, 'taskId' => 1.5]]], 'tasks.0.taskId'],
        ];
    }

    /** @param  array<mixed>  $data */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(array $data, string $errorKey): void
    {
        try {
            AssignmentInput::validate($data);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->errors());
        }
    }
}

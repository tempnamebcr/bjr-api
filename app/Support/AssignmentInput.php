<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The one gate between untrusted input and the domain. Every entry point (bundled dataset,
 * CLI files, HTTP payload) goes through the same rules, so they all fail the same way.
 */
final readonly class AssignmentInput
{
    /**
     * @param  list<Employee>  $employees
     * @param  list<Task>  $tasks
     */
    public function __construct(
        public array $employees,
        public array $tasks,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'employees' => ['present', 'list'],
            'employees.*' => ['array:employeeId,skillLevel,hourlyRate'],
            'employees.*.employeeId' => ['required', 'integer', 'distinct'],
            'employees.*.skillLevel' => ['required', 'integer', 'min:1'],
            'employees.*.hourlyRate' => ['required', 'numeric', 'min:0'],

            'tasks' => ['present', 'list'],
            'tasks.*' => ['array:taskId,skillLevel,estimation'],
            'tasks.*.taskId' => ['required', 'integer', 'distinct'],
            'tasks.*.skillLevel' => ['required', 'integer', 'min:1'],
            'tasks.*.estimation' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @param  array<mixed>  $data  raw {employees, tasks}
     *
     * @throws ValidationException
     */
    public static function validate(array $data): self
    {
        return self::fromValidated(Validator::make($data, self::rules())->validate());
    }

    /**
     * Casts are explicit because the `integer`/`numeric` rules also accept numeric strings ("5"),
     * while the domain constructors are strictly typed.
     *
     * @param  array{employees: list<array<string, mixed>>, tasks: list<array<string, mixed>>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            array_map(fn (array $e): Employee => new Employee((int) $e['employeeId'], (int) $e['skillLevel'], (float) $e['hourlyRate']), $validated['employees']),
            array_map(fn (array $t): Task => new Task((int) $t['taskId'], (int) $t['skillLevel'], (float) $t['estimation']), $validated['tasks']),
        );
    }
}

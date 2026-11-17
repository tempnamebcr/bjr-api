<?php

declare(strict_types=1);

namespace App\Repositories;

use JsonException;
use RuntimeException;

/**
 * Reads an employees/tasks dataset from two JSON files (the bundled sample by default,
 * or files given to the CLI). Returns raw records: validation happens in one place,
 * AssignmentInput, for every entry point (bundled data, CLI files, HTTP payload).
 */
final readonly class DatasetRepository
{
    /** The brief's sample data, relative to resources/. */
    public const SAMPLE_EMPLOYEES = 'datasets/employees.json';

    public const SAMPLE_TASKS = 'datasets/tasks.json';

    public function __construct(
        private string $employeesPath,
        private string $tasksPath,
    ) {}

    /**
     * @return array{employees: list<mixed>, tasks: list<mixed>}
     *
     * @throws RuntimeException when a file is missing or isn't a JSON array
     */
    public function load(): array
    {
        return [
            'employees' => $this->read($this->employeesPath),
            'tasks' => $this->read($this->tasksPath),
        ];
    }

    /** @return list<mixed> */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Dataset file not found: {$path}");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Dataset file is not valid JSON: {$path}", previous: $e);
        }

        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException("Dataset file must contain a JSON array: {$path}");
        }

        return $data;
    }
}

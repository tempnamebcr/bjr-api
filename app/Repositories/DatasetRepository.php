<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use JsonException;
use RuntimeException;

/**
 * Loads the bundled sample dataset (employees.json / tasks.json) as domain objects.
 * The only data source in the app, so it's the only repository: the domain never touches files.
 */
final readonly class DatasetRepository
{
    public function __construct(private string $directory) {}

    /** @return list<Employee> */
    public function employees(): array
    {
        return array_map(Employee::fromArray(...), $this->read('employees.json'));
    }

    /** @return list<Task> */
    public function tasks(): array
    {
        return array_map(Task::fromArray(...), $this->read('tasks.json'));
    }

    /** @return list<array<string, mixed>> */
    private function read(string $file): array
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.$file;

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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Contracts\SolvesAssignments;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

/**
 * Decorator: same interface as the solver, adds a result cache (Redis in Docker).
 *
 * Safe because solving is deterministic: same input + same algorithm version → same result.
 * The key is built from a canonical form of the input (sorted by id), so reordered but
 * identical inputs share one entry.
 *
 * A cache outage must not take the API down: errors are reported and the solver runs uncached.
 */
final readonly class CachedAssignmentSolver implements SolvesAssignments
{
    public function __construct(
        private SolvesAssignments $solver,
        private Cache $cache,
        private int $ttlSeconds,
    ) {}

    public function solve(array $tasks, array $employees, Variant $variant): AssignmentResult
    {
        $key = self::key($tasks, $employees, $variant);

        try {
            $cached = $this->cache->get($key);
        } catch (Throwable $e) {
            report($e);

            return $this->solver->solve($tasks, $employees, $variant);
        }

        if ($cached instanceof AssignmentResult) {
            return $cached;
        }

        $result = $this->solver->solve($tasks, $employees, $variant);

        try {
            $this->cache->put($key, $result, $this->ttlSeconds);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * @param  list<Task>  $tasks
     * @param  list<Employee>  $employees
     */
    public static function key(array $tasks, array $employees, Variant $variant): string
    {
        $canonicalTasks = array_map(fn (Task $t): array => [$t->id, $t->skillLevel, $t->estimation], $tasks);
        $canonicalEmployees = array_map(fn (Employee $e): array => [$e->id, $e->skillLevel, $e->hourlyRate], $employees);
        sort($canonicalTasks);
        sort($canonicalEmployees);

        $hash = hash('sha256', (string) json_encode([$canonicalTasks, $canonicalEmployees], JSON_PRESERVE_ZERO_FRACTION));

        return sprintf('assignment:v%d:%s:%s', AssignmentSolver::ALGORITHM_VERSION, $variant->value, $hash);
    }
}

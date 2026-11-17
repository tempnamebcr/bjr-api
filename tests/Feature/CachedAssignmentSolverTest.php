<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Contracts\SolvesAssignments;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Domain\Assignment\Enums\Variant;
use App\Services\CachedAssignmentSolver;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** Feature-level because it uses report() and the container. */
final class CachedAssignmentSolverTest extends TestCase
{
    private CountingSolver $inner;

    private CachedAssignmentSolver $cached;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inner = new CountingSolver(new AssignmentSolver);
        $this->cached = new CachedAssignmentSolver($this->inner, new Repository(new ArrayStore(serializesValues: true)), 60);
    }

    public function test_a_repeated_solve_is_served_from_the_cache(): void
    {
        [$tasks, $employees] = $this->smallInstance();

        $first = $this->cached->solve($tasks, $employees, Variant::Makespan);
        $second = $this->cached->solve($tasks, $employees, Variant::Makespan);

        $this->assertSame(1, $this->inner->calls);
        $this->assertEquals($first, $second);
    }

    public function test_reordered_but_identical_input_shares_the_cache_entry(): void
    {
        [$tasks, $employees] = $this->smallInstance();

        $this->cached->solve($tasks, $employees, Variant::Cost);
        $this->cached->solve(array_reverse($tasks), array_reverse($employees), Variant::Cost);

        $this->assertSame(1, $this->inner->calls);
    }

    public function test_each_variant_has_its_own_entry(): void
    {
        [$tasks, $employees] = $this->smallInstance();

        $this->cached->solve($tasks, $employees, Variant::Cost);
        $this->cached->solve($tasks, $employees, Variant::Makespan);

        $this->assertSame(2, $this->inner->calls);
    }

    public function test_any_input_change_misses_the_cache(): void
    {
        [$tasks, $employees] = $this->smallInstance();

        $this->cached->solve($tasks, $employees, Variant::Cost);
        $this->cached->solve([...$tasks, new Task(99, 1, 1)], $employees, Variant::Cost);
        $this->cached->solve($tasks, [new Employee(1, 1, 101), ...array_slice($employees, 1)], Variant::Cost);

        $this->assertSame(3, $this->inner->calls);
    }

    public function test_the_key_includes_the_algorithm_version(): void
    {
        [$tasks, $employees] = $this->smallInstance();

        $this->assertStringStartsWith(
            'assignment:v'.AssignmentSolver::ALGORITHM_VERSION.':cost:',
            CachedAssignmentSolver::key($tasks, $employees, Variant::Cost),
        );
    }

    public function test_a_cache_outage_falls_back_to_solving_and_is_reported(): void
    {
        Exceptions::fake();
        $broken = Mockery::mock(Cache::class);
        $broken->shouldReceive('get')->andThrow(new RuntimeException('Redis is down'));
        $solver = new CachedAssignmentSolver($this->inner, $broken, 60);
        [$tasks, $employees] = $this->smallInstance();

        $result = $solver->solve($tasks, $employees, Variant::Cost);

        $this->assertTrue($result->isFeasible());
        $this->assertSame(1, $this->inner->calls);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Redis is down');
    }

    public function test_the_container_wires_the_cache_around_the_solver(): void
    {
        $this->assertInstanceOf(CachedAssignmentSolver::class, $this->app->make(SolvesAssignments::class));
    }

    /** @return array{list<Task>, list<Employee>} */
    private function smallInstance(): array
    {
        return [
            [new Task(1, 1, 2), new Task(2, 2, 3), new Task(3, 1, 1)],
            [new Employee(1, 1, 100), new Employee(2, 2, 300)],
        ];
    }
}

/** Test double that counts how often the real solver actually runs. */
final class CountingSolver implements SolvesAssignments
{
    public int $calls = 0;

    public function __construct(private readonly SolvesAssignments $solver) {}

    public function solve(array $tasks, array $employees, Variant $variant): AssignmentResult
    {
        $this->calls++;

        return $this->solver->solve($tasks, $employees, $variant);
    }
}

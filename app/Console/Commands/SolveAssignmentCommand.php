<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Enums\Variant;
use App\Http\Resources\AssignmentResponseResource;
use App\Repositories\DatasetRepository;
use App\Support\AssignmentInput;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * CLI entry point. Same validation (AssignmentInput) and the same JSON contract
 * (AssignmentResponseResource, with --json) as the HTTP API.
 *
 * Exit codes: 0 solved (feasible or infeasible), 1 unreadable file, 2 invalid input.
 */
final class SolveAssignmentCommand extends Command
{
    protected $signature = 'assignment:solve
        {variant=all : cost, makespan or all}
        {--employees= : Path to an employees JSON file (default: the bundled sample)}
        {--tasks= : Path to a tasks JSON file (default: the bundled sample)}
        {--json : Print the API JSON response instead of tables}';

    protected $description = 'Assign tasks to employees using the min-cost and/or min-makespan variant';

    public function handle(AssignmentSolver $solver): int
    {
        $variants = $this->variants();
        if ($variants === null) {
            $this->error("Unknown variant \"{$this->argument('variant')}\". Use cost, makespan or all.");

            return self::INVALID;
        }

        $dataset = new DatasetRepository(
            $this->option('employees') ?? resource_path(DatasetRepository::SAMPLE_EMPLOYEES),
            $this->option('tasks') ?? resource_path(DatasetRepository::SAMPLE_TASKS),
        );

        try {
            $input = AssignmentInput::validate($dataset->load());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->error('Invalid input:');
            foreach ($e->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::INVALID;
        }

        $results = array_map(fn (Variant $v) => $solver->solve($input->tasks, $input->employees, $v), $variants);

        if ($this->option('json')) {
            $response = (new AssignmentResponseResource(['input' => $input, 'results' => $results]))->resolve();
            $this->line((string) json_encode($response, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION));

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $this->render($result);
        }

        return self::SUCCESS;
    }

    /** @return list<Variant>|null null when the argument is not a known variant */
    private function variants(): ?array
    {
        $argument = (string) $this->argument('variant');

        if ($argument === 'all') {
            return Variant::cases();
        }

        $variant = Variant::tryFrom($argument);

        return $variant === null ? null : [$variant];
    }

    private function render(AssignmentResult $result): void
    {
        $title = $result->variant === Variant::Cost ? 'Minimum total cost' : 'Minimum makespan';
        $this->newLine();
        $this->info("{$title} ({$result->variant->value}): {$result->status->value}");

        if (! $result->isFeasible()) {
            $this->warn('  No eligible employee for task(s): '.implode(', ', $result->unassignableTaskIds));

            return;
        }

        $this->line(sprintf(
            '  Total price: %s   Makespan: %s h   Lower bound: %s h%s',
            number_format((float) $result->totalPrice, 2),
            number_format((float) $result->makespan, 2),
            number_format((float) $result->lowerBound, 2),
            $result->isProvenOptimalMakespan() ? ' (makespan proven optimal)' : '',
        ));

        $tasksByEmployee = [];
        foreach ($result->assignments as $assignment) {
            $tasksByEmployee[$assignment->employeeId][] = $assignment->taskId;
        }

        $rows = [];
        foreach ($result->employeeLoads as $load) {
            $rows[] = [
                $load->employeeId,
                number_format($load->totalAssignedHours, 2),
                implode(', ', $tasksByEmployee[$load->employeeId] ?? []) ?: '-',
            ];
        }

        $this->table(['Employee', 'Assigned hours', 'Tasks'], $rows);
    }
}

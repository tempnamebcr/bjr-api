<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SolveAssignmentCommandTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->tempFiles);
        parent::tearDown();
    }

    public function test_it_solves_the_sample_dataset_for_both_variants(): void
    {
        $this->artisan('assignment:solve')
            ->expectsOutputToContain('Minimum total cost (cost): feasible')
            ->expectsOutputToContain('Total price: 17,460.00   Makespan: 51.00 h')
            ->expectsOutputToContain('Minimum makespan (makespan): feasible')
            ->expectsOutputToContain('Makespan: 11.00 h   Lower bound: 11.00 h (makespan proven optimal)')
            ->assertExitCode(0);
    }

    public function test_it_can_run_a_single_variant(): void
    {
        $this->artisan('assignment:solve cost')
            ->expectsOutputToContain('Minimum total cost')
            ->doesntExpectOutputToContain('Minimum makespan')
            ->assertExitCode(0);
    }

    public function test_json_output_matches_the_api_contract(): void
    {
        $exitCode = Artisan::call('assignment:solve', ['variant' => 'makespan', '--json' => true]);
        $json = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(['input', 'variants'], array_keys($json));
        $this->assertSame(['makespan'], array_keys($json['variants']));
        $this->assertEquals(11, $json['variants']['makespan']['makespan']);
        $this->assertCount(30, $json['variants']['makespan']['assignments']);
    }

    public function test_it_reads_custom_files_and_reports_infeasible_tasks(): void
    {
        $employees = $this->tempJson([['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100]]);
        $tasks = $this->tempJson([
            ['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1],
            ['taskId' => 2, 'skillLevel' => 3, 'estimation' => 1],
        ]);

        $this->artisan('assignment:solve', ['--employees' => $employees, '--tasks' => $tasks])
            ->expectsOutputToContain('(cost): infeasible')
            ->expectsOutputToContain('No eligible employee for task(s): 2')
            ->assertExitCode(0);
    }

    public function test_an_unknown_variant_is_invalid(): void
    {
        $this->artisan('assignment:solve fastest')
            ->expectsOutputToContain('Unknown variant "fastest"')
            ->assertExitCode(2);
    }

    public function test_a_missing_file_fails(): void
    {
        $this->artisan('assignment:solve', ['--employees' => '/nope/employees.json'])
            ->expectsOutputToContain('Dataset file not found')
            ->assertExitCode(1);
    }

    public function test_invalid_file_content_is_reported_per_field(): void
    {
        $employees = $this->tempJson([['employeeId' => 1, 'skillLevel' => 0, 'hourlyRate' => 100]]);

        $this->artisan('assignment:solve', ['--employees' => $employees])
            ->expectsOutputToContain('employees.0.skillLevel')
            ->assertExitCode(2);
    }

    /** @param  list<array<string, int|float>>  $data */
    private function tempJson(array $data): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'assignment');
        file_put_contents($path, json_encode($data));
        $this->tempFiles[] = $path;

        return $path;
    }
}

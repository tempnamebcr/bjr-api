<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class AssignmentEndpointTest extends TestCase
{
    private const VARIANT_STRUCTURE = [
        'variant',
        'status',
        'assignments' => [['taskId', 'employeeId', 'start', 'end']],
        'totalPrice',
        'makespan',
        'employees' => [['employeeId', 'totalAssignedHours']],
        'unassignableTaskIds',
        'meta' => ['lowerBound', 'provenOptimal'],
    ];

    public function test_get_solves_the_sample_dataset_for_both_variants(): void
    {
        $this->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonStructure([
                'input' => [
                    'employees' => [['employeeId', 'skillLevel', 'hourlyRate']],
                    'tasks' => [['taskId', 'skillLevel', 'estimation']],
                ],
                'variants' => ['cost' => self::VARIANT_STRUCTURE, 'makespan' => self::VARIANT_STRUCTURE],
            ])
            ->assertJsonCount(10, 'input.employees')
            ->assertJsonCount(30, 'input.tasks')
            ->assertJsonPath('variants.cost.status', 'feasible')
            ->assertJsonPath('variants.cost.totalPrice', 17460)
            ->assertJsonPath('variants.cost.makespan', 51)
            ->assertJsonCount(30, 'variants.cost.assignments')
            ->assertJsonCount(10, 'variants.cost.employees')
            ->assertJsonPath('variants.makespan.makespan', 11)
            ->assertJsonPath('variants.makespan.meta.lowerBound', 11)
            ->assertJsonPath('variants.makespan.meta.provenOptimal', true);
    }

    public function test_get_can_select_a_single_variant(): void
    {
        $this->getJson('/api/v1/assignments?variant=cost')
            ->assertOk()
            ->assertJsonPath('variants.cost.variant', 'cost')
            ->assertJsonMissingPath('variants.makespan');
    }

    public function test_get_rejects_an_unknown_variant(): void
    {
        $this->getJson('/api/v1/assignments?variant=fastest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant');
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/assignments?variant=fastest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant');
    }

    public function test_post_solves_the_given_payload(): void
    {
        $this->postJson('/api/v1/assignments', [
            'employees' => [
                ['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100],
                ['employeeId' => 2, 'skillLevel' => 2, 'hourlyRate' => 1000],
            ],
            'tasks' => [
                ['taskId' => 1, 'skillLevel' => 1, 'estimation' => 2],
                ['taskId' => 2, 'skillLevel' => 1, 'estimation' => 2],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('variants.cost.totalPrice', 400)
            ->assertJsonPath('variants.cost.makespan', 4)
            ->assertJsonPath('variants.makespan.makespan', 2)
            ->assertJsonPath('variants.makespan.totalPrice', 2200)
            ->assertJsonPath('variants.makespan.assignments', [
                ['taskId' => 1, 'employeeId' => 1, 'start' => 0, 'end' => 2],
                ['taskId' => 2, 'employeeId' => 2, 'start' => 0, 'end' => 2],
            ]);
    }

    public function test_post_can_request_a_subset_of_variants(): void
    {
        $this->postJson('/api/v1/assignments', [...$this->tinyPayload(), 'variants' => ['makespan']])
            ->assertOk()
            ->assertJsonPath('variants.makespan.status', 'feasible')
            ->assertJsonMissingPath('variants.cost');
    }

    public function test_an_infeasible_instance_is_a_200_with_the_unassignable_task_ids(): void
    {
        $this->postJson('/api/v1/assignments', [
            'employees' => [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100]],
            'tasks' => [
                ['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1],
                ['taskId' => 2, 'skillLevel' => 3, 'estimation' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('variants.cost.status', 'infeasible')
            ->assertJsonPath('variants.cost.unassignableTaskIds', [2])
            ->assertJsonPath('variants.cost.assignments', [])
            ->assertJsonPath('variants.cost.totalPrice', null)
            ->assertJsonPath('variants.cost.makespan', null)
            ->assertJsonPath('variants.makespan.status', 'infeasible');
    }

    public function test_post_rejects_malformed_input(): void
    {
        $this->postJson('/api/v1/assignments', [
            'employees' => [
                ['employeeId' => 1, 'skillLevel' => 0, 'hourlyRate' => 100],
                ['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => -5],
            ],
            'tasks' => [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 0]],
            'variants' => ['cheapest'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employees.0.skillLevel',
                'employees.0.employeeId',
                'employees.1.hourlyRate',
                'tasks.0.estimation',
                'variants.0',
            ]);
    }

    public function test_post_requires_employees_and_tasks(): void
    {
        $this->postJson('/api/v1/assignments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employees', 'tasks']);
    }

    public function test_fractional_values_are_rounded_to_two_decimals(): void
    {
        $this->postJson('/api/v1/assignments', [
            'employees' => [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 33.333]],
            'tasks' => [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1.5]],
            'variants' => ['cost'],
        ])
            ->assertOk()
            ->assertJsonPath('variants.cost.totalPrice', 50)
            ->assertJsonPath('variants.cost.makespan', 1.5);
    }

    /** @return array<string, mixed> */
    private function tinyPayload(): array
    {
        return [
            'employees' => [['employeeId' => 1, 'skillLevel' => 1, 'hourlyRate' => 100]],
            'tasks' => [['taskId' => 1, 'skillLevel' => 1, 'estimation' => 1]],
        ];
    }
}

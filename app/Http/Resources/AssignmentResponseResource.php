<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\Employee;
use App\Domain\Assignment\Data\Task;
use App\Support\AssignmentInput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Top-level response: the input echoed back (so the frontend can join skill/rate onto each
 * assignment without a second call) plus one result per requested variant, keyed by variant.
 *
 * @property array{input: AssignmentInput, results: list<AssignmentResult>} $resource
 */
final class AssignmentResponseResource extends JsonResource
{
    /** No {"data": …} envelope: the contract is the object itself. */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $input = $this->resource['input'];

        $variants = [];
        foreach ($this->resource['results'] as $result) {
            $variants[$result->variant->value] = (new AssignmentResultResource($result))->resolve($request);
        }

        return [
            'input' => [
                'employees' => array_map(fn (Employee $e): array => [
                    'employeeId' => $e->id,
                    'skillLevel' => $e->skillLevel,
                    'hourlyRate' => $e->hourlyRate,
                ], $input->employees),
                'tasks' => array_map(fn (Task $t): array => [
                    'taskId' => $t->id,
                    'skillLevel' => $t->skillLevel,
                    'estimation' => $t->estimation,
                ], $input->tasks),
            ],
            'variants' => $variants,
        ];
    }
}

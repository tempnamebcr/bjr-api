<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Assignment\Data\Assignment;
use App\Domain\Assignment\Data\AssignmentResult;
use App\Domain\Assignment\Data\EmployeeLoad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The API contract for one variant. The frontend's TypeScript types mirror this shape.
 * Numbers are rounded to 2 decimals here, at the edge, never inside the domain.
 *
 * @property AssignmentResult $resource
 */
final class AssignmentResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            'variant' => $result->variant->value,
            'status' => $result->status->value,
            'assignments' => array_map(fn (Assignment $a): array => [
                'taskId' => $a->taskId,
                'employeeId' => $a->employeeId,
                'start' => self::round($a->start),
                'end' => self::round($a->end),
            ], $result->assignments),
            'totalPrice' => self::round($result->totalPrice),
            'makespan' => self::round($result->makespan),
            'employees' => array_map(fn (EmployeeLoad $load): array => [
                'employeeId' => $load->employeeId,
                'totalAssignedHours' => self::round($load->totalAssignedHours),
            ], $result->employeeLoads),
            'unassignableTaskIds' => $result->unassignableTaskIds,
            'meta' => [
                'lowerBound' => self::round($result->lowerBound),
                // Named for the makespan: on the cost variant it is usually false, while the cost itself is always optimal.
                'makespanProvenOptimal' => $result->isProvenOptimalMakespan(),
            ],
        ];
    }

    private static function round(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}

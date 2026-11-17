<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assignment\AssignmentSolver;
use App\Domain\Assignment\Enums\Variant;
use App\Http\Controllers\Controller;
use App\Http\Requests\SolveAssignmentRequest;
use App\Http\Resources\AssignmentResponseResource;
use App\Repositories\DatasetRepository;
use App\Support\AssignmentInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Thin HTTP shell: validate → solve each requested variant → shape the response.
 * Infeasible instances are valid answers (200); only malformed input is a 422.
 */
final class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentSolver $solver) {}

    /** GET /api/v1/assignments?variant=cost|makespan|all — solves the bundled sample dataset. */
    public function index(Request $request, DatasetRepository $dataset): AssignmentResponseResource
    {
        $validated = $request->validate([
            'variant' => ['sometimes', Rule::in(['all', ...array_column(Variant::cases(), 'value')])],
        ]);

        $variant = $validated['variant'] ?? 'all';
        $variants = $variant === 'all' ? Variant::cases() : [Variant::from($variant)];

        return $this->respond(AssignmentInput::validate($dataset->load()), $variants);
    }

    /** POST /api/v1/assignments — solves the given {employees, tasks, variants?} payload. */
    public function store(SolveAssignmentRequest $request): AssignmentResponseResource
    {
        return $this->respond($request->assignmentInput(), $request->variants());
    }

    /** @param  list<Variant>  $variants */
    private function respond(AssignmentInput $input, array $variants): AssignmentResponseResource
    {
        $results = array_map(
            fn (Variant $variant) => $this->solver->solve($input->tasks, $input->employees, $variant),
            $variants,
        );

        return new AssignmentResponseResource(['input' => $input, 'results' => $results]);
    }
}

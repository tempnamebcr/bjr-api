<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Assignment\Enums\Variant;
use App\Support\AssignmentInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SolveAssignmentRequest extends FormRequest
{
    /** No auth in this app. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...AssignmentInput::rules(),
            'variants' => ['sometimes', 'list', 'min:1'],
            'variants.*' => ['distinct', Rule::enum(Variant::class)],
        ];
    }

    public function assignmentInput(): AssignmentInput
    {
        return AssignmentInput::fromValidated($this->validated());
    }

    /** @return list<Variant> defaults to every variant */
    public function variants(): array
    {
        $requested = $this->validated('variants');

        return $requested === null ? Variant::cases() : array_map(Variant::from(...), $requested);
    }
}

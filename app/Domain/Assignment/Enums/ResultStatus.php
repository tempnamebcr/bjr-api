<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Enums;

enum ResultStatus: string
{
    case Feasible = 'feasible';
    case Infeasible = 'infeasible';
}

<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Enums;

enum Variant: string
{
    case Cost = 'cost';
    case Makespan = 'makespan';
}

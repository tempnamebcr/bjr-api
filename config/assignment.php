<?php

return [

    /*
    | How long a solved result stays cached (seconds). Results are deterministic,
    | so a cached result is always identical to a fresh one for the same input.
    */
    'cache_ttl' => (int) env('ASSIGNMENT_CACHE_TTL', 86400),

    /*
    | Input size limits. The makespan local search grows super-linearly
    | (~0.1 s at 1 000 × 50, ~2 s at 2 000 × 100, ~40 s at 5 000 × 200),
    | so these bound the worst-case time a single request can take.
    */
    'max_tasks' => (int) env('ASSIGNMENT_MAX_TASKS', 1000),

    'max_employees' => (int) env('ASSIGNMENT_MAX_EMPLOYEES', 100),

];

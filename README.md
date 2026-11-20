# Task Assignment API

Laravel 12 backend for the task → employee assignment case. It solves two optimization variants and serves them over a CLI and an HTTP API:

- **Minimum total cost** (exact)
- **Minimum makespan** (heuristic, with a lower bound that proves optimality when it's reached)

A task can only go to an employee with `skillLevel >= task.skillLevel`. Each employee works their tasks one after another, and everyone starts at t = 0.

On the sample data from the brief:

| Variant | Total price | Makespan | Lower bound |
|---|---|---|---|
| Min cost | 17 460 | 51 h | 11 h |
| Min makespan | 25 050 | **11 h** (proven optimal) | 11 h |

## Running it

### With Docker

The `docker-compose.yml` lives one level up, next to this folder and the Nuxt frontend (`bjr-front`).

```bash
docker compose up --build
```

- The API runs on http://localhost:8000.
- Redis is the result cache.
- On first start, the entrypoint installs dependencies and creates `.env` with an app key.

### Locally (PHP 8.2+, Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve            # http://127.0.0.1:8000
```

`.env.example` uses the `database` cache store. Either run `touch database/database.sqlite && php artisan migrate` once, or set `CACHE_STORE=file` in `.env`. Tests don't need either, because they use the `array` store.

## Usage

### CLI

```bash
php artisan assignment:solve                     # both variants, bundled sample data
php artisan assignment:solve cost                # one variant: cost | makespan | all
php artisan assignment:solve --json              # the exact HTTP response body
php artisan assignment:solve --employees=path/to/employees.json --tasks=path/to/tasks.json
```

Each variant prints a summary (price, makespan, lower bound) and a per-employee table (hours, task ids).

Exit codes:
- `0`: solved, including an infeasible instance
- `1`: a file can't be read
- `2`: the input is invalid

### HTTP

| Method | Path | Body / query | Solves |
|---|---|---|---|
| `GET` | `/api/v1/assignments` | `?variant=cost\|makespan\|all` (default `all`) | the bundled sample data |
| `POST` | `/api/v1/assignments` | `{ "employees": [...], "tasks": [...], "variants": ["cost", "makespan"] }` (`variants` optional) | the given payload |

```bash
curl http://localhost:8000/api/v1/assignments?variant=makespan

curl -X POST http://localhost:8000/api/v1/assignments \
  -H 'Content-Type: application/json' \
  -d '{"employees":[{"employeeId":1,"skillLevel":2,"hourlyRate":100}],
       "tasks":[{"taskId":1,"skillLevel":1,"estimation":3}]}'
```

### Response contract

```jsonc
{
  "input": { "employees": [ ... ], "tasks": [ ... ] },      // echoed, so a client can join skill/rate per row
  "variants": {
    "cost": {
      "variant": "cost",
      "status": "feasible",                                   // or "infeasible"
      "assignments": [{ "taskId": 1, "employeeId": 8, "start": 0, "end": 2 }],
      "totalPrice": 17460,                                    // Σ hourlyRate × estimation
      "makespan": 51,                                         // max employee load
      "employees": [{ "employeeId": 1, "totalAssignedHours": 0 }],   // every employee, idle ones at 0
      "unassignableTaskIds": [],
      "meta": { "lowerBound": 11, "makespanProvenOptimal": false }  // makespan == lowerBound → no schedule finishes earlier
    },
    "makespan": { "...": "same shape" }
  }
}
```

- **Infeasible** (a task needs a higher skill than any employee has): HTTP **200** with `status: "infeasible"`. The response also has `unassignableTaskIds`, empty `assignments`, and `null` totals.
- **Malformed input:** HTTP **422** with per-field errors. API errors are always JSON.
- **Assignment times:** `start`/`end` place each task on its employee's timeline, back to back in task-id order.
- **Rounding:** numbers are rounded to 2 decimals in the response only.

## How it works

### Minimum total cost: exact, O(T log T + T·E)

Cost is `Σ rate × estimation`, there's no capacity limit, and stacking work costs nothing extra. So the objective splits per task, and giving every task to its **cheapest eligible employee** is optimal.

Ties between equally cheap employees go to the least-loaded one, then the lower id. That doesn't change the cost, but it keeps the makespan from growing for nothing.

### Minimum makespan: heuristic plus lower bound

Balancing load across machines with eligibility rules is NP-hard. Skill levels are *nested* (a level-3 employee can do anything), and the approach uses that.

1. **Lower bound.**
   - Formula: `max(longest task, max over k of hours needing skill ≥ k / employees with skill ≥ k)`.
   - It rounds up when all durations are whole hours.
   - If the makespan reaches it, the schedule is **proven optimal**.
2. **Hierarchical LPT.**
   - Tasks go in order of skill (high to low), then duration (long to short).
   - Each one goes to the least-loaded eligible employee.
   - Senior-only work is placed while seniors are free; small, flexible tasks fill the gaps last.
3. **Local search.**
   - Move or swap tasks off a critical employee while the makespan drops. This fixes cases plain LPT misses, such as `3,3,2,2,2` on two workers: 7 → 6.
   - Stops as soon as it reaches the lower bound.
4. **Cost pass.**
   - With the makespan fixed, move tasks to cheaper employees who have room.
   - On the sample data this cut the price from 26 300 to 25 050 at the same 11 h.

**Determinism:**
- Input is sorted by id.
- Every comparison has a full tie-break chain that ends in ids.
- There's no randomness and no wall-clock time limit (only a step limit).

The same input always gives the same output, including when the input is reordered (this is tested).

## Architecture

```
HTTP (AssignmentController) ─┐
CLI  (assignment:solve)     ─┼─▶ AssignmentInput ─▶ SolvesAssignments ─▶ AssignmentResponseResource
Bundled data (DatasetRepository) ┘   (one gate:       (CachedAssignmentSolver      (the JSON contract)
                                       validate+map)    └▶ AssignmentSolver)
```

| Path | Role |
|---|---|
| `app/Domain/Assignment/` | **Pure PHP**, no Laravel imports, so it's unit-tested without booting the framework |
| `├─ AssignmentSolver.php` | Service: feasibility → strategy → result |
| `├─ Strategies/` | `MinCostStrategy`, `MinMakespanStrategy` (Strategy pattern, one per variant) |
| `├─ Support/` | `FeasibilityChecker`, `MakespanLowerBound`, `MakespanImprover`, `ResultBuilder` |
| `├─ Data/`, `Enums/` | Readonly value objects that validate themselves; enums for variant and status |
| `app/Services/CachedAssignmentSolver.php` | Decorator: caches results (Redis) by input hash + algorithm version. A cache outage solves uncached |
| `app/Support/AssignmentInput.php` | The one validation gate for every entry point, including size limits |
| `app/Repositories/DatasetRepository.php` | Reads the bundled sample (`resources/datasets/`) or CLI files |
| `app/Http/` | Thin controller, form request, and the API Resources (the contract) |

**Why this shape.** The difficult part of the case is the algorithms, not data access. Keeping them framework-free makes them easy to test in isolation and to reuse from the CLI, HTTP and, if ever needed, a queued job. Adding a variant means writing one strategy class.

## Tests

```bash
php artisan test
```

There are about 105 tests. The brief's edge cases map to them like this:

| Brief edge case | Where |
|---|---|
| Task with no eligible employee | `FeasibilityCheckerTest`, `AssignmentSolverTest`, endpoint and CLI tests |
| Widely varying hourly rates | `MinCostStrategyTest` (1 vs 10 000) |
| Highly skewed durations | `MinMakespanStrategyTest`, `MakespanLowerBoundTest` (one 100 h task + 50 × 1 h → makespan = LB = 100) |
| 50–200 tasks, 5–30 employees | `AssignmentSolverPerformanceTest` (seeded instances; both variants < 1 s; prints timings) |
| Deterministic output | `AssignmentSolverPerformanceTest` (same input twice and shuffled → identical result) |

## Performance and scaling

Measured locally on seeded random instances. The makespan search reached the lower bound in every run.

| Tasks × employees | Min cost | Min makespan |
|---|---|---|
| 200 × 30 (brief maximum) | ~2 ms | ~6 ms |
| 1 000 × 50 | 14 ms | 126 ms |
| 2 000 × 100 | 38 ms | 1.8 s |
| 5 000 × 200 | 183 ms | 38 s |

The local search grows super-linearly, so input is capped at **1 000 tasks / 100 employees** (configurable). That bounds how long one request can take.

**Scaling path, in order:**
1. The solve is a pure, CPU-bound function, so stateless replicas scale it horizontally. The Redis cache shares results across them, which is safe because output is deterministic.
2. Octane removes framework boot cost per request.
3. Bigger instances or an exact solver: move the solve to a queued job (Horizon is installed) with polling. The cache decorator is where that change would go.
4. Real-world constraints (time windows, travel, dependencies): a dedicated solver such as OR-Tools / CP-SAT behind the same API contract.

**Possible algorithm improvements:**
- Incremental candidate tracking in the local search instead of full rescans.
- Per-skill-tier heaps for the least-loaded lookup.
- An exact cost-at-fixed-makespan step.

## Configuration

| Env | Default | Purpose |
|---|---|---|
| `CACHE_STORE` | `redis` (Docker) | Store for solved results |
| `ASSIGNMENT_CACHE_TTL` | `86400` | Result cache lifetime in seconds. Bump `AssignmentSolver::ALGORITHM_VERSION` when the algorithm changes |
| `ASSIGNMENT_MAX_TASKS` | `1000` | Input size limit |
| `ASSIGNMENT_MAX_EMPLOYEES` | `100` | Input size limit |

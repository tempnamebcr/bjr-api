<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('assignments', [AssignmentController::class, 'index']);
    Route::post('assignments', [AssignmentController::class, 'store']);
});

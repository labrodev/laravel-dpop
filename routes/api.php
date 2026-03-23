<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Labrodev\Dpop\Http\Controllers\TokenController;

Route::post((string) config('dpop.token_route', 'api/dpop/token'), TokenController::class)
    ->name('dpop.token');

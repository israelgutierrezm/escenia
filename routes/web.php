<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Escenia is an API-first control plane; the browser SPAs live under apps/*.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
    'api' => url('/api/v1'),
]));

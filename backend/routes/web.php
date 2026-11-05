<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => 'Cloud Cost Tracker',
    'api' => '/api',
    'health' => '/api/health',
]));

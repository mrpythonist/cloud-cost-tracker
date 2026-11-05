<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Billing CSV uploads
Route::get('/uploads', [UploadController::class, 'index']);
Route::post('/uploads', [UploadController::class, 'store']);

// Reporting
Route::get('/reports/monthly', [ReportController::class, 'monthly']);
Route::get('/reports/compare', [ReportController::class, 'compare']);
Route::get('/reports/trends', [ReportController::class, 'trends']);

// Budgets
Route::get('/budgets', [BudgetController::class, 'index']);
Route::post('/budgets', [BudgetController::class, 'store']);
Route::put('/budgets/{budget}', [BudgetController::class, 'update']);
Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy']);

// Budget alerts
Route::get('/alerts', [AlertController::class, 'index']);
Route::post('/alerts/check', [AlertController::class, 'check']);
Route::patch('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);

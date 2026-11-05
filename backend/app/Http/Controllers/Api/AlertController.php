<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** GET /api/alerts?level=warning|exceeded&acknowledged=true|false */
    public function index(Request $request): JsonResponse
    {
        $query = BudgetAlert::with('budget')->orderByDesc('created_at');
        if ($request->query('level')) {
            $query->where('level', $request->query('level'));
        }
        if ($request->query('acknowledged') === 'true') {
            $query->whereNotNull('acknowledged_at');
        } elseif ($request->query('acknowledged') === 'false') {
            $query->whereNull('acknowledged_at');
        }
        return response()->json($query->paginate(20));
    }

    /** POST /api/alerts/check — re-evaluate all budgets for months that have data. */
    public function check(): JsonResponse
    {
        $months = \App\Models\BillingRecord::distinct()->orderBy('month')->pluck('month')->all();
        $created = app(UploadController::class)->recheckBudgets($months);
        return response()->json(['alerts_created' => $created, 'months_checked' => $months]);
    }

    /** PATCH /api/alerts/{id}/acknowledge */
    public function acknowledge(BudgetAlert $alert): JsonResponse
    {
        $alert->update(['acknowledged_at' => now()]);
        return response()->json($alert);
    }
}

<?php

return [
    /*
    | Cost Tracker settings. All thresholds are configurable via .env so the
    | same code can be tuned without edits.
    */
    'increase_threshold_percent' => (float) env('COST_INCREASE_THRESHOLD_PERCENT', 20),
    'budget_warning_threshold' => (float) env('BUDGET_WARNING_THRESHOLD', 0.80),
    'budget_exceeded_threshold' => (float) env('BUDGET_EXCEEDED_THRESHOLD', 1.00),
    'max_csv_bytes' => 10 * 1024 * 1024, // 10 MB, mirrored in CsvParser
];

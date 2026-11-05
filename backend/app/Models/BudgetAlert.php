<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'period',
        'service',
        'level',
        'threshold_percent',
        'actual',
        'budget_amount',
        'usage_percent',
        'message',
        'acknowledged_at',
    ];

    protected $casts = [
        'actual' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'usage_percent' => 'decimal:2',
        'acknowledged_at' => 'datetime',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}

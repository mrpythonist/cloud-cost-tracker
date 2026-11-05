<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'service',
        'amount',
        'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function alerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class, 'budget_id');
    }

    /** Overall monthly budget when service is null. */
    public function isOverall(): bool
    {
        return $this->service === null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_upload_id',
        'month',
        'date',
        'service',
        'usage_type',
        'cost',
        'currency',
        'region',
    ];

    protected $casts = [
        'date' => 'date',
        'cost' => 'decimal:4',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(BillingUpload::class, 'billing_upload_id');
    }
}

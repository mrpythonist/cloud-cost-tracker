<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'stored_path',
        'storage_disk',
        'file_size_bytes',
        'format',
        'status',
        'rows_imported',
        'rows_skipped',
        'total_cost',
        'currency',
        'months_covered',
        'parse_report',
    ];

    protected $casts = [
        'months_covered' => 'array',
        'parse_report' => 'array',
        'total_cost' => 'decimal:2',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(BillingRecord::class, 'billing_upload_id');
    }
}

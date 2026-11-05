<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_upload_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM, derived from date
            $table->date('date');
            $table->string('service', 100); // normalised, e.g. EC2 (was AmazonEC2)
            $table->string('usage_type')->nullable();
            $table->decimal('cost', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('region')->nullable();
            $table->timestamps();

            $table->index(['month', 'service']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};

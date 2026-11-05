<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('service', 100)->nullable(); // null = overall budget
            $table->string('level'); // warning (80%) | exceeded (100%)
            $table->unsignedTinyInteger('threshold_percent');
            $table->decimal('actual', 12, 2);
            $table->decimal('budget_amount', 12, 2);
            $table->decimal('usage_percent', 8, 2);
            $table->text('message');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'level']);
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};

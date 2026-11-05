<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7); // YYYY-MM
            $table->string('service', 100)->nullable(); // null = overall monthly budget
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['period', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};

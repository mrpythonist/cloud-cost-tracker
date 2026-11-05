<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('storage_disk')->default('local');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('format')->default('aws-cur'); // aws-cur | simple
            $table->string('status')->default('completed'); // completed | failed
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('months_covered')->nullable();
            $table->json('parse_report')->nullable(); // skipped rows + reasons
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_uploads');
    }
};

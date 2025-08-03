<?php

// ===========================
// 3. database/migrations/xxxx_create_stacking_areas_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stacking_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // AREA-A1, AREA-B2
            $table->string('name');
            $table->text('location')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stacking_areas');
    }
};
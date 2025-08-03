<?php

// ===========================
// 2. database/migrations/xxxx_create_equipment_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // DT-001, EX-001
            $table->enum('type', ['dumptruck', 'excavator']);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->decimal('capacity', 8, 2)->nullable(); // ton untuk dumptruck, m3 untuk excavator
            $table->year('year_manufacture')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
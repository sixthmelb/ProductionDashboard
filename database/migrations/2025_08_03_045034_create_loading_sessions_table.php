<?php

// ===========================
// 4. database/migrations/xxxx_create_loading_sessions_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loading_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code', 100)->unique(); // LS-2024-001
            $table->foreignId('stacking_area_id')->constrained('stacking_areas');
            $table->foreignId('user_id')->constrained('users'); // MCR yang memulai
            $table->enum('shift', ['A', 'B', 'C']);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->integer('total_buckets')->default(0);
            $table->decimal('total_tonnage', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_time']);
            $table->index(['shift', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_sessions');
    }
};

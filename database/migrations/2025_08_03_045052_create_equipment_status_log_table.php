<?php

// ===========================
// 7. database/migrations/xxxx_create_equipment_status_log_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_status_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->enum('status', ['idle', 'working', 'breakdown', 'maintenance']);
            $table->foreignId('loading_session_id')->nullable()->constrained('loading_sessions');
            $table->string('location')->nullable(); // GPS atau area description
            $table->string('operator_name')->nullable();
            $table->decimal('fuel_level', 3, 1)->nullable(); // percentage
            $table->decimal('engine_hours', 8, 1)->nullable();
            $table->timestamp('status_time')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'status_time']);
            $table->index(['status', 'status_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_status_log');
    }
};
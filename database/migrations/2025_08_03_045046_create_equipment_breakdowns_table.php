<?php

// ===========================
// 6. database/migrations/xxxx_create_equipment_breakdowns_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->foreignId('loading_session_id')->nullable()->constrained('loading_sessions');
            $table->enum('breakdown_type', ['mechanical', 'electrical', 'hydraulic', 'engine', 'tire', 'other']);
            $table->text('description');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->nullable(); // akan di-calculate otomatis
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->decimal('repair_cost', 10, 2)->default(0);
            $table->string('repaired_by')->nullable();
            $table->enum('status', ['ongoing', 'repaired', 'pending_parts'])->default('ongoing');
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamps();

            $table->index(['equipment_id', 'start_time']);
            $table->index(['status', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_breakdowns');
    }
};
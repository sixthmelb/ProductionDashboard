<?php

// ===========================
// 5. database/migrations/xxxx_create_bucket_activities_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bucket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loading_session_id')->constrained('loading_sessions')->onDelete('cascade');
            $table->foreignId('excavator_id')->constrained('equipment');
            $table->foreignId('dumptruck_id')->constrained('equipment');
            $table->integer('bucket_count')->default(1);
            $table->decimal('estimated_tonnage', 8, 2)->nullable();
            $table->timestamp('activity_time')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loading_session_id', 'activity_time']);
            $table->index(['excavator_id', 'activity_time']);
            $table->index(['dumptruck_id', 'activity_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_activities');
    }
};

<?php
// ===========================
// database/migrations/2025_08_03_120000_add_performance_indexes.php
// Migration yang dihasilkan dari: php artisan make:migration add_performance_indexes
// Kemudian dimodifikasi untuk dashboard performance optimization
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PERFORMANCE: Menambahkan composite indexes untuk dashboard queries
     */
    public function up(): void
    {
        // INDEX: Equipment status logs untuk current status queries
        Schema::table('equipment_status_log', function (Blueprint $table) {
            // CRITICAL: Index untuk getCurrentStatusAttribute() di Equipment model
            // Query pattern: WHERE equipment_id = ? ORDER BY status_time DESC, id DESC LIMIT 1
            $table->index(['equipment_id', 'status_time', 'id'], 'equipment_current_status_idx');
            
            // DASHBOARD: Index untuk real-time status monitoring
            // Query pattern: WHERE status = ? AND status_time BETWEEN ? AND ?
            $table->index(['status', 'status_time'], 'status_time_monitoring_idx');
            
            // SESSION: Index untuk session-based equipment tracking
            // Query pattern: WHERE loading_session_id = ? ORDER BY status_time DESC
            $table->index(['loading_session_id', 'status_time'], 'session_equipment_status_idx');
            
            // FUEL: Index untuk fuel level warnings
            // Query pattern: WHERE fuel_level < ? AND fuel_level IS NOT NULL
            $table->index(['fuel_level', 'equipment_id'], 'fuel_level_warning_idx');
        });

        // INDEX: Equipment breakdowns untuk dashboard analytics
        Schema::table('equipment_breakdowns', function (Blueprint $table) {
            // CRITICAL: Index untuk canWork() method dan active breakdowns
            // Query pattern: WHERE equipment_id = ? AND status IN ('ongoing', 'pending_parts')
            $table->index(['equipment_id', 'status', 'start_time'], 'equipment_active_breakdown_idx');
            
            // ANALYTICS: Index untuk breakdown analysis charts
            // Query pattern: WHERE breakdown_type = ? AND start_time BETWEEN ? AND ?
            $table->index(['breakdown_type', 'start_time', 'severity'], 'breakdown_analytics_idx');
            
            // PERFORMANCE: Index untuk MTTR calculations
            // Query pattern: WHERE status = 'repaired' AND duration_minutes IS NOT NULL
            $table->index(['status', 'start_time', 'duration_minutes'], 'mttr_calculation_idx');
            
            // DASHBOARD: Index untuk severity-based alerts
            // Query pattern: WHERE severity = 'critical' AND status IN ('ongoing', 'pending_parts')
            $table->index(['severity', 'status', 'start_time'], 'critical_breakdown_idx');
        });

        // INDEX: Bucket activities untuk production metrics
        Schema::table('bucket_activities', function (Blueprint $table) {
            // PRODUCTION: Index untuk session totals calculation
            // Query pattern: WHERE loading_session_id = ? (untuk updateSessionTotals)
            $table->index(['loading_session_id', 'estimated_tonnage', 'bucket_count'], 'session_totals_idx');
            
            // ANALYTICS: Index untuk equipment productivity analysis
            // Query pattern: WHERE excavator_id = ? AND activity_time BETWEEN ? AND ?
            $table->index(['excavator_id', 'activity_time', 'estimated_tonnage'], 'excavator_productivity_idx');
            $table->index(['dumptruck_id', 'activity_time', 'estimated_tonnage'], 'dumptruck_productivity_idx');
            
            // DASHBOARD: Index untuk daily/hourly production charts
            // Query pattern: WHERE activity_time BETWEEN ? AND ? ORDER BY activity_time
            $table->index(['activity_time', 'estimated_tonnage', 'bucket_count'], 'production_timeline_idx');
            
            // TRENDS: Index untuk production trend analysis
            // Query pattern: WHERE DATE(activity_time) = ? GROUP BY HOUR(activity_time)
            $table->index(['activity_time'], 'activity_time_grouping_idx');
        });

        // INDEX: Loading sessions untuk dashboard performance
        Schema::table('loading_sessions', function (Blueprint $table) {
            // DASHBOARD: Index untuk active sessions monitoring
            // Query pattern: WHERE status = 'active' ORDER BY start_time DESC
            $table->index(['status', 'start_time'], 'active_sessions_monitoring_idx');
            
            // ANALYTICS: Index untuk shift performance comparison
            // Query pattern: WHERE shift = ? AND start_time BETWEEN ? AND ?
            $table->index(['shift', 'start_time', 'status'], 'shift_performance_idx');
            
            // REPORTS: Index untuk area-based analytics
            // Query pattern: WHERE stacking_area_id = ? AND start_time BETWEEN ? AND ?
            $table->index(['stacking_area_id', 'start_time', 'status'], 'area_performance_idx');
            
            // COMPLETION: Index untuk completion rate calculations
            // Query pattern: WHERE status = 'completed' AND end_time IS NOT NULL
            $table->index(['status', 'end_time', 'start_time'], 'completion_rate_idx');
        });

        // INDEX: Equipment untuk dashboard filtering
        Schema::table('equipment', function (Blueprint $table) {
            // DASHBOARD: Index untuk equipment filtering dan grouping
            // Query pattern: WHERE type = ? AND status = 'active'
            $table->index(['type', 'status'], 'equipment_type_status_idx');
            
            // CAPACITY: Index untuk capacity-based analysis
            // Query pattern: WHERE type = ? ORDER BY capacity DESC
            $table->index(['type', 'capacity'], 'equipment_capacity_idx');
            
            // SEARCH: Index untuk equipment search functionality
            // Query pattern: WHERE code LIKE ? OR brand LIKE ?
            $table->index(['code'], 'equipment_code_search_idx');
            $table->index(['brand', 'model'], 'equipment_brand_model_idx');
        });

        // INDEX: Stacking areas untuk session analytics
        Schema::table('stacking_areas', function (Blueprint $table) {
            // ACTIVE: Index untuk active areas filtering
            // Query pattern: WHERE is_active = true
            $table->index(['is_active', 'code'], 'active_areas_idx');
            
            // LOCATION: Index untuk location-based queries (jika GPS features added)
            // Query pattern: WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            $table->index(['latitude', 'longitude'], 'location_coordinates_idx');
        });
    }

    /**
     * Reverse the migrations.
     * CLEANUP: Menghapus semua indexes yang ditambahkan
     */
    public function down(): void
    {
        // Drop equipment_status_log indexes
        Schema::table('equipment_status_log', function (Blueprint $table) {
            $table->dropIndex('equipment_current_status_idx');
            $table->dropIndex('status_time_monitoring_idx');
            $table->dropIndex('session_equipment_status_idx');
            $table->dropIndex('fuel_level_warning_idx');
        });

        // Drop equipment_breakdowns indexes
        Schema::table('equipment_breakdowns', function (Blueprint $table) {
            $table->dropIndex('equipment_active_breakdown_idx');
            $table->dropIndex('breakdown_analytics_idx');
            $table->dropIndex('mttr_calculation_idx');
            $table->dropIndex('critical_breakdown_idx');
        });

        // Drop bucket_activities indexes
        Schema::table('bucket_activities', function (Blueprint $table) {
            $table->dropIndex('session_totals_idx');
            $table->dropIndex('excavator_productivity_idx');
            $table->dropIndex('dumptruck_productivity_idx');
            $table->dropIndex('production_timeline_idx');
            $table->dropIndex('activity_time_grouping_idx');
        });

        // Drop loading_sessions indexes
        Schema::table('loading_sessions', function (Blueprint $table) {
            $table->dropIndex('active_sessions_monitoring_idx');
            $table->dropIndex('shift_performance_idx');
            $table->dropIndex('area_performance_idx');
            $table->dropIndex('completion_rate_idx');
        });

        // Drop equipment indexes
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex('equipment_type_status_idx');
            $table->dropIndex('equipment_capacity_idx');
            $table->dropIndex('equipment_code_search_idx');
            $table->dropIndex('equipment_brand_model_idx');
        });

        // Drop stacking_areas indexes
        Schema::table('stacking_areas', function (Blueprint $table) {
            $table->dropIndex('active_areas_idx');
            $table->dropIndex('location_coordinates_idx');
        });
    }
};

<?php
// ===========================
// 1. database/migrations/xxxx_add_role_shift_to_users_table.php
// ===========================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'mcr', 'manager'])->default('mcr')->after('email');
            $table->enum('shift', ['A', 'B', 'C'])->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('shift');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'shift', 'is_active']);
        });
    }
};

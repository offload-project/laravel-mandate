<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds optional columns to permissions and roles tables for syncing
     * metadata from Mandate attributes. Enable in config/mandate.php:
     *
     * 'sync_columns' => true,              // All columns
     * 'sync_columns' => ['set', 'label'],  // Specific columns
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        // Add columns to permissions table
        if (Schema::hasTable($tableNames['permissions'])) {
            Schema::table($tableNames['permissions'], function (Blueprint $table) {
                $table->string('set')->nullable()->after('guard_name')->index();
                $table->string('label')->nullable()->after('set');
                $table->text('description')->nullable();
            });
        }

        // Add columns to roles table
        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) {
                $table->string('set')->nullable()->after('guard_name')->index();
                $table->string('label')->nullable()->after('set');
                $table->text('description')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        if (Schema::hasTable($tableNames['permissions'])) {
            Schema::table($tableNames['permissions'], function (Blueprint $table) {
                $table->dropColumn(['set', 'label', 'description']);
            });
        }

        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) {
                $table->dropColumn(['set', 'label', 'description']);
            });
        }
    }
};

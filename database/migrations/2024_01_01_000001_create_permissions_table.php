<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('mandate.tables.permissions', 'permissions');
        $contextEnabled = config('mandate.context.enabled', false);

        Schema::create($tableName, function (Blueprint $table) use ($contextEnabled) {
            $table->id();
            $table->string('name');
            $table->string('guard');

            if ($contextEnabled) {
                $contextTypeColumn = config('mandate.column_names.context_morph_type', 'context_type');
                $contextIdColumn = config('mandate.column_names.context_morph_key', 'context_id');

                $table->string($contextTypeColumn)->nullable();
                $table->unsignedBigInteger($contextIdColumn)->nullable();

                $table->unique(['name', 'guard', $contextTypeColumn, $contextIdColumn], 'permissions_unique');
                $table->index([$contextTypeColumn, $contextIdColumn], 'permissions_context_index');
            } else {
                $table->unique(['name', 'guard']);
            }

            $table->timestamps();
            $table->index('guard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.permissions', 'permissions');

        Schema::dropIfExists($tableName);
    }
};

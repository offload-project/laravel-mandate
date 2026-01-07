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
        $tableName = config('mandate.tables.role_subject', 'role_subject');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');
        $subjectIdColumn = config('mandate.column_names.subject_morph_key', 'subject_id');
        $subjectTypeColumn = config('mandate.column_names.subject_morph_type', 'subject_type');

        Schema::create($tableName, function (Blueprint $table) use ($rolesTable, $roleIdColumn, $subjectIdColumn, $subjectTypeColumn) {
            $table->foreignId($roleIdColumn)
                ->constrained($rolesTable)
                ->cascadeOnDelete();

            $table->unsignedBigInteger($subjectIdColumn);
            $table->string($subjectTypeColumn);

            $table->timestamps();

            $table->primary([$roleIdColumn, $subjectIdColumn, $subjectTypeColumn], 'role_subject_primary');
            $table->index([$subjectIdColumn, $subjectTypeColumn], 'role_subject_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.role_subject', 'role_subject');

        Schema::dropIfExists($tableName);
    }
};

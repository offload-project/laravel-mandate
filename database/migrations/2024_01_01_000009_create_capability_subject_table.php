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
        $tableName = config('mandate.tables.capability_subject', 'capability_subject');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $subjectIdColumn = config('mandate.column_names.subject_morph_key', 'subject_id');
        $subjectTypeColumn = config('mandate.column_names.subject_morph_type', 'subject_type');

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $capabilityIdColumn, $subjectIdColumn, $subjectTypeColumn) {
            $table->foreignId($capabilityIdColumn)
                ->constrained($capabilitiesTable)
                ->cascadeOnDelete();

            $table->unsignedBigInteger($subjectIdColumn);
            $table->string($subjectTypeColumn);

            $table->timestamps();

            $table->primary([$capabilityIdColumn, $subjectIdColumn, $subjectTypeColumn], 'capability_subject_primary');
            $table->index([$subjectIdColumn, $subjectTypeColumn], 'capability_subject_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.capability_subject', 'capability_subject');

        Schema::dropIfExists($tableName);
    }
};

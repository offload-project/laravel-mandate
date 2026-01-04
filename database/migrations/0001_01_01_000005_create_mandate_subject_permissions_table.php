<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('mandate.tables.subject_permissions', 'mandate_subject_permissions');
        $permissionsTable = config('mandate.tables.permissions', 'mandate_permissions');
        $idType = config('mandate.id_type', 'bigint');
        $permissionKey = config('mandate.columns.pivot_permission_key', 'permission_id');
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
        $addContext = config('mandate.context.subject_permissions', false);

        Schema::create($tableName, function (Blueprint $table) use ($idType, $permissionKey, $subjectMorphKey, $contextMorphName, $permissionsTable, $addContext) {
            if ($idType === 'uuid') {
                $table->uuid($permissionKey);
                $table->uuidMorphs($subjectMorphKey);
            } else {
                $table->unsignedBigInteger($permissionKey);
                $table->numericMorphs($subjectMorphKey);
            }

            $table->foreign($permissionKey)
                ->references('id')
                ->on($permissionsTable)
                ->cascadeOnDelete();

            if ($addContext) {
                $table->string('scope')->nullable()->index();
                if ($idType === 'uuid') {
                    $table->nullableUuidMorphs($contextMorphName);
                } else {
                    $table->nullableNumericMorphs($contextMorphName);
                }
            }

            $table->timestamps();

            // Composite index for efficient lookups
            $table->index(["{$subjectMorphKey}_type", "{$subjectMorphKey}_id"], 'subject_permissions_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.subject_permissions', 'mandate_subject_permissions'));
    }
};

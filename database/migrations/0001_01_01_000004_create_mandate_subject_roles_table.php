<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('mandate.tables.subject_roles', 'mandate_subject_roles');
        $rolesTable = config('mandate.tables.roles', 'mandate_roles');
        $idType = config('mandate.id_type', 'bigint');
        $roleKey = config('mandate.columns.pivot_role_key', 'role_id');
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
        $addContext = config('mandate.context.subject_roles', false);

        Schema::create($tableName, function (Blueprint $table) use ($idType, $roleKey, $subjectMorphKey, $contextMorphName, $rolesTable, $addContext) {
            if ($idType === 'uuid') {
                $table->uuid($roleKey);
                $table->uuidMorphs($subjectMorphKey);
            } else {
                $table->unsignedBigInteger($roleKey);
                $table->numericMorphs($subjectMorphKey);
            }

            $table->foreign($roleKey)
                ->references('id')
                ->on($rolesTable)
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
            $table->index(["{$subjectMorphKey}_type", "{$subjectMorphKey}_id"], 'subject_roles_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.subject_roles', 'mandate_subject_roles'));
    }
};

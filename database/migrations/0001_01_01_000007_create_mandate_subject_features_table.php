<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if features are disabled
        if (! config('mandate.features.enabled', true)) {
            return;
        }

        $tableName = config('mandate.tables.subject_features', 'mandate_subject_features');
        $featuresTable = config('mandate.tables.features', 'mandate_features');
        $idType = config('mandate.id_type', 'bigint');
        $featureKey = config('mandate.columns.pivot_feature_key', 'feature_id');
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
        $addContext = config('mandate.context.subject_features', false);

        Schema::create($tableName, function (Blueprint $table) use ($idType, $featureKey, $subjectMorphKey, $contextMorphName, $featuresTable, $addContext) {
            if ($idType === 'uuid') {
                $table->uuid($featureKey);
                $table->uuidMorphs($subjectMorphKey);
            } else {
                $table->unsignedBigInteger($featureKey);
                $table->numericMorphs($subjectMorphKey);
            }

            $table->foreign($featureKey)
                ->references('id')
                ->on($featuresTable)
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
            $table->index(["{$subjectMorphKey}_type", "{$subjectMorphKey}_id"], 'subject_features_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.subject_features', 'mandate_subject_features'));
    }
};

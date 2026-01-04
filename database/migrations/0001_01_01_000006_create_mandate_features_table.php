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

        $tableName = config('mandate.tables.features', 'mandate_features');
        $idType = config('mandate.id_type', 'bigint');
        $addContext = config('mandate.context.features', false);
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

        Schema::create($tableName, function (Blueprint $table) use ($idType, $addContext, $contextMorphName) {
            if ($idType === 'uuid') {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $table->string('name');
            $table->string('scope')->nullable()->index();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();

            if ($addContext) {
                $table->string('scope')->nullable()->index();
                $table->string("{$contextMorphName}_type")->nullable();
                if ($idType === 'uuid') {
                    $table->uuid("{$contextMorphName}_id")->nullable();
                } else {
                    $table->unsignedBigInteger("{$contextMorphName}_id")->nullable();
                }
                $table->index(["{$contextMorphName}_type", "{$contextMorphName}_id"], 'features_context_model_index');
            }

            $table->timestamps();

            $table->unique(['name', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.features', 'mandate_features'));
    }
};

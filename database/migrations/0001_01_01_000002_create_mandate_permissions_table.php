<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('mandate.tables.permissions', 'mandate_permissions');
        $idType = config('mandate.id_type', 'bigint');
        $addContext = config('mandate.context.permissions', false);
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

        Schema::create($tableName, function (Blueprint $table) use ($idType, $addContext, $contextMorphName) {
            if ($idType === 'uuid') {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $table->string('name');
            $table->string('guard_name');
            $table->string('set')->nullable()->index();
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
                $table->index(["{$contextMorphName}_type", "{$contextMorphName}_id"], 'permissions_context_model_index');
            }

            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.permissions', 'mandate_permissions'));
    }
};

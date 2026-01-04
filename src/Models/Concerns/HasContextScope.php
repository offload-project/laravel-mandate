<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides context scoping functionality for models.
 */
trait HasContextScope
{
    /**
     * Scope query to a specific scope.
     */
    public function scopeForScope(Builder $query, ?string $scope = null, Model|string|null $contextModel = null): Builder
    {
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

        if ($scope !== null) {
            $query->where('scope', $scope);
        } else {
            $query->whereNull('scope');
        }

        if ($contextModel !== null) {
            if ($contextModel instanceof Model) {
                $query->where("{$contextMorphName}_type", $contextModel->getMorphClass())
                    ->where("{$contextMorphName}_id", $contextModel->getKey());
            } else {
                // String passed as context model type with no ID means filter by type only
                $query->where("{$contextMorphName}_type", $contextModel);
            }
        } else {
            $query->whereNull("{$contextMorphName}_type")
                ->whereNull("{$contextMorphName}_id");
        }

        return $query;
    }

    /**
     * Scope query to include a specific scope or global (null scope).
     */
    public function scopeWithScope(Builder $query, ?string $scope = null, Model|string|null $contextModel = null): Builder
    {
        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

        return $query->where(function (Builder $q) use ($scope, $contextModel, $contextMorphName) {
            // Include global (null scope)
            $q->where(function (Builder $q) use ($contextMorphName) {
                $q->whereNull('scope')
                    ->whereNull("{$contextMorphName}_type")
                    ->whereNull("{$contextMorphName}_id");
            });

            // Include specific scope if provided
            if ($scope !== null || $contextModel !== null) {
                $q->orWhere(function (Builder $q) use ($scope, $contextModel, $contextMorphName) {
                    if ($scope !== null) {
                        $q->where('scope', $scope);
                    }

                    if ($contextModel instanceof Model) {
                        $q->where("{$contextMorphName}_type", $contextModel->getMorphClass())
                            ->where("{$contextMorphName}_id", $contextModel->getKey());
                    } elseif ($contextModel !== null) {
                        $q->where("{$contextMorphName}_type", $contextModel);
                    }
                });
            }
        });
    }

    /**
     * Check if context columns are enabled for this model's table.
     */
    public function hasContextColumns(): bool
    {
        $table = $this->getTable();
        $tables = config('mandate.tables', []);
        $contextConfig = config('mandate.context', []);

        // Find the config key for this table
        foreach ($tables as $key => $tableName) {
            if ($tableName === $table && isset($contextConfig[$key])) {
                return (bool) $contextConfig[$key];
            }
        }

        return false;
    }

    /**
     * Get the scope value.
     */
    public function getScope(): ?string
    {
        return $this->hasContextColumns() ? $this->getAttribute('scope') : null;
    }

    /**
     * Get the context model.
     */
    public function getContextModel(): ?Model
    {
        if (! $this->hasContextColumns()) {
            return null;
        }

        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

        $type = $this->getAttribute("{$contextMorphName}_type");
        $id = $this->getAttribute("{$contextMorphName}_id");

        if ($type === null || $id === null) {
            return null;
        }

        return $type::find($id);
    }
}

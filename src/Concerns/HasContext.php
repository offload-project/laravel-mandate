<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait providing context-aware functionality for permissions and roles.
 *
 * Context allows scoping permissions and roles to a polymorphic model,
 * enabling multi-tenancy and resource-specific authorization.
 */
trait HasContext
{
    /**
     * Check if context model support is enabled.
     */
    protected function contextEnabled(): bool
    {
        return config('mandate.context.enabled', false);
    }

    /**
     * Check if global fallback is enabled for context checks.
     */
    protected function globalFallbackEnabled(): bool
    {
        return config('mandate.context.global_fallback', true);
    }

    /**
     * Get the context type column name.
     */
    protected function getContextTypeColumn(): string
    {
        return config('mandate.column_names.context_morph_type', 'context_type');
    }

    /**
     * Get the context ID column name.
     */
    protected function getContextIdColumn(): string
    {
        return config('mandate.column_names.context_morph_key', 'context_id');
    }

    /**
     * Extract context type and ID from a model.
     *
     * @return array{type: string|null, id: int|string|null}
     */
    protected function resolveContext(?Model $context): array
    {
        if ($context === null) {
            return ['type' => null, 'id' => null];
        }

        return [
            'type' => $context->getMorphClass(),
            'id' => $context->getKey(),
        ];
    }

    /**
     * Apply context conditions to a pivot query.
     *
     * @param  MorphToMany<Model>  $query
     * @return MorphToMany<Model>
     */
    protected function withContext(MorphToMany $query, ?Model $context): MorphToMany
    {
        if (! $this->contextEnabled()) {
            return $query;
        }

        $resolved = $this->resolveContext($context);
        $typeColumn = $this->getContextTypeColumn();
        $idColumn = $this->getContextIdColumn();

        return $query->wherePivot($typeColumn, $resolved['type'])
            ->wherePivot($idColumn, $resolved['id']);
    }

    /**
     * Apply context conditions with global fallback to a pivot query.
     *
     * @param  MorphToMany<Model>  $query
     * @return MorphToMany<Model>
     */
    protected function withContextOrGlobal(MorphToMany $query, ?Model $context): MorphToMany
    {
        if (! $this->contextEnabled()) {
            return $query;
        }

        $resolved = $this->resolveContext($context);
        $typeColumn = $this->getContextTypeColumn();
        $idColumn = $this->getContextIdColumn();

        // If no context provided, just query for global (null context)
        if ($context === null) {
            return $query->wherePivot($typeColumn, null)
                ->wherePivot($idColumn, null);
        }

        // If global fallback enabled, query for either context or global
        if ($this->globalFallbackEnabled()) {
            return $query->where(function ($q) use ($typeColumn, $idColumn, $resolved) {
                $pivotTable = $q->getModel()->getTable();
                $q->where(function ($inner) use ($pivotTable, $typeColumn, $idColumn, $resolved) {
                    $inner->where("{$pivotTable}.{$typeColumn}", $resolved['type'])
                        ->where("{$pivotTable}.{$idColumn}", $resolved['id']);
                })->orWhere(function ($inner) use ($pivotTable, $typeColumn, $idColumn) {
                    $inner->whereNull("{$pivotTable}.{$typeColumn}")
                        ->whereNull("{$pivotTable}.{$idColumn}");
                });
            });
        }

        // No fallback, just query for specific context
        return $query->wherePivot($typeColumn, $resolved['type'])
            ->wherePivot($idColumn, $resolved['id']);
    }

    /**
     * Get pivot data including context information.
     *
     * @return array<string, mixed>
     */
    protected function getContextPivotData(?Model $context): array
    {
        if (! $this->contextEnabled()) {
            return [];
        }

        $resolved = $this->resolveContext($context);

        return [
            $this->getContextTypeColumn() => $resolved['type'],
            $this->getContextIdColumn() => $resolved['id'],
        ];
    }

    /**
     * Check if a pivot record exists with specific context.
     */
    protected function pivotExistsWithContext(
        MorphToMany $relation,
        int|string $relatedId,
        ?Model $context
    ): bool {
        $query = $relation->newPivotStatementForId($relatedId);

        if ($this->contextEnabled()) {
            $resolved = $this->resolveContext($context);
            $query->where($this->getContextTypeColumn(), $resolved['type'])
                ->where($this->getContextIdColumn(), $resolved['id']);
        }

        return $query->exists();
    }

    /**
     * Sync a single relation with context support.
     *
     * @param  array<int|string>  $ids
     */
    protected function syncWithContext(
        MorphToMany $relation,
        array $ids,
        ?Model $context,
        bool $detaching = true
    ): void {
        if (! $this->contextEnabled()) {
            if ($detaching) {
                $relation->sync($ids);
            } else {
                $relation->syncWithoutDetaching($ids);
            }

            return;
        }

        $pivotData = $this->getContextPivotData($context);

        // Build the sync array with pivot data
        $syncData = [];
        foreach ($ids as $id) {
            $syncData[$id] = $pivotData;
        }

        if ($detaching) {
            // For full sync with context, we need to detach only matching context
            $this->detachWithContext($relation, null, $context);
            $relation->attach($syncData);
        } else {
            // Sync without detaching
            foreach ($syncData as $id => $attributes) {
                if (! $this->pivotExistsWithContext($relation, $id, $context)) {
                    $relation->attach($id, $attributes);
                }
            }
        }
    }

    /**
     * Attach relations with context support.
     *
     * @param  array<int|string>  $ids
     */
    protected function attachWithContext(
        MorphToMany $relation,
        array $ids,
        ?Model $context
    ): void {
        $pivotData = $this->getContextPivotData($context);

        foreach ($ids as $id) {
            if (! $this->pivotExistsWithContext($relation, $id, $context)) {
                $relation->attach($id, $pivotData);
            }
        }
    }

    /**
     * Detach relations with context support.
     *
     * @param  array<int|string>|null  $ids  Null to detach all matching context
     */
    protected function detachWithContext(
        MorphToMany $relation,
        ?array $ids,
        ?Model $context
    ): void {
        if (! $this->contextEnabled()) {
            $relation->detach($ids);

            return;
        }

        $resolved = $this->resolveContext($context);
        $query = $relation->newPivotQuery();

        // Add context conditions
        $query->where($this->getContextTypeColumn(), $resolved['type'])
            ->where($this->getContextIdColumn(), $resolved['id']);

        // Filter by specific IDs if provided
        if ($ids !== null) {
            // Use the pivot key column name, not the related model's key name
            $relatedPivotKey = $relation->getRelatedPivotKeyName();
            $query->whereIn($relatedPivotKey, $ids);
        }

        $query->delete();
    }
}

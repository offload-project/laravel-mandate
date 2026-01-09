<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Builder;
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
     * Get the subject morph name (base name without suffix).
     */
    protected function getSubjectMorphName(): string
    {
        return config('mandate.column_names.subject_morph_name', 'subject');
    }

    /**
     * Get the subject type column name.
     */
    protected function getSubjectTypeColumn(): string
    {
        return $this->getSubjectMorphName().'_type';
    }

    /**
     * Get the subject ID column name.
     */
    protected function getSubjectIdColumn(): string
    {
        return $this->getSubjectMorphName().'_id';
    }

    /**
     * Get the context morph name (base name without suffix).
     */
    protected function getContextMorphName(): string
    {
        return config('mandate.column_names.context_morph_name', 'context');
    }

    /**
     * Get the context type column name.
     */
    protected function getContextTypeColumn(): string
    {
        return $this->getContextMorphName().'_type';
    }

    /**
     * Get the context ID column name.
     */
    protected function getContextIdColumn(): string
    {
        return $this->getContextMorphName().'_id';
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
     * Apply context constraints to a query with optional global fallback.
     *
     * This method centralizes the repeated context query logic used throughout
     * the permission and role traits.
     *
     * @param  MorphToMany<Model>|Builder<Model>  $query
     * @param  string  $pivotTable  The name of the pivot table for building column references
     * @return MorphToMany<Model>|Builder<Model>
     */
    protected function applyContextConstraints(
        MorphToMany|Builder $query,
        ?Model $context,
        string $pivotTable
    ): MorphToMany|Builder {
        if (! $this->contextEnabled()) {
            return $query;
        }

        $resolved = $this->resolveContext($context);
        $typeCol = $this->getContextTypeColumn();
        $idCol = $this->getContextIdColumn();

        // If no context provided, just query for global (null context)
        if ($context === null) {
            return $query->wherePivot($typeCol, null)
                ->wherePivot($idCol, null);
        }

        // If global fallback is enabled, query for either context or global
        if ($this->globalFallbackEnabled()) {
            return $query->where(function ($q) use ($pivotTable, $typeCol, $idCol, $resolved) {
                $q->where(function ($inner) use ($pivotTable, $typeCol, $idCol, $resolved) {
                    $inner->where("{$pivotTable}.{$typeCol}", $resolved['type'])
                        ->where("{$pivotTable}.{$idCol}", $resolved['id']);
                })->orWhere(function ($inner) use ($pivotTable, $typeCol, $idCol) {
                    $inner->whereNull("{$pivotTable}.{$typeCol}")
                        ->whereNull("{$pivotTable}.{$idCol}");
                });
            });
        }

        // No fallback, just query for specific context
        return $query->wherePivot($typeCol, $resolved['type'])
            ->wherePivot($idCol, $resolved['id']);
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
            // Sync without detaching - use optimized batch attach
            $this->attachWithContext($relation, $ids, $context);
        }
    }

    /**
     * Attach relations with context support.
     *
     * Uses batch operations for better performance instead of individual queries.
     *
     * @param  array<int|string>  $ids
     */
    protected function attachWithContext(
        MorphToMany $relation,
        array $ids,
        ?Model $context
    ): void {
        if (empty($ids)) {
            return;
        }

        $pivotData = $this->getContextPivotData($context);

        // Get existing IDs in a single query for better performance
        $query = $relation->newPivotQuery()->whereIn($relation->getRelatedPivotKeyName(), $ids);

        if ($this->contextEnabled()) {
            $resolved = $this->resolveContext($context);
            $query->where($this->getContextTypeColumn(), $resolved['type'])
                ->where($this->getContextIdColumn(), $resolved['id']);
        }

        $existingIds = $query->pluck($relation->getRelatedPivotKeyName())->all();

        // Filter to only new IDs
        $newIds = array_diff($ids, $existingIds);

        if (! empty($newIds)) {
            // Bulk attach all new IDs at once
            $attachData = [];
            foreach ($newIds as $id) {
                $attachData[$id] = $pivotData;
            }
            $relation->attach($attachData);
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

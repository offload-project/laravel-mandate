<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for feature access checking.
 *
 * This interface defines the methods that a feature management package (e.g., Flagged)
 * must implement to integrate with Mandate's permission system. When a Feature model
 * is used as a context, Mandate will delegate to this handler to verify feature access
 * before evaluating scoped permissions.
 */
interface FeatureAccessHandler
{
    /**
     * Check if a feature is globally active.
     *
     * This checks whether the feature is enabled at a system level,
     * regardless of any specific subject.
     */
    public function isActive(Model $feature): bool;

    /**
     * Check if a subject has been granted access to a feature.
     *
     * This checks whether the subject has explicit access to the feature,
     * separate from whether the feature is active. A feature might be active
     * but the subject may not have been granted access to it.
     */
    public function hasAccess(Model $feature, Model $subject): bool;

    /**
     * Check both feature activation and subject access in a single call.
     *
     * This is a convenience method that combines isActive() and hasAccess()
     * checks. Returns true only if the feature is globally active AND
     * the subject has been granted access.
     *
     * Implementations may optimize this to reduce redundant checks.
     */
    public function canAccess(Model $feature, Model $subject): bool;
}

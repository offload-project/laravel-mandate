<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Exceptions\FeatureAccessException;

/**
 * Trait for checking feature access before permission/role checks.
 *
 * This trait provides methods to determine if a context model is a Feature
 * and to verify feature access via the FeatureAccessHandler contract.
 */
trait ChecksFeatureAccess
{
    /**
     * Check if feature integration is enabled.
     */
    protected function featureIntegrationEnabled(): bool
    {
        return config('mandate.features.enabled', false)
            && config('mandate.context.enabled', false);
    }

    /**
     * Check if the given context is a Feature model.
     */
    protected function isFeatureContext(?Model $context): bool
    {
        if ($context === null) {
            return false;
        }

        $featureModels = config('mandate.features.models', []);

        if (empty($featureModels)) {
            return false;
        }

        foreach ($featureModels as $featureModel) {
            if ($context instanceof $featureModel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the subject can access the feature context.
     *
     * Returns true if:
     * - Feature integration is disabled
     * - Context is not a Feature model
     * - Feature access check passes
     *
     * Returns false if:
     * - Handler is missing and on_missing_handler is 'deny'
     * - Feature access check fails
     *
     * @throws FeatureAccessException If handler is missing and on_missing_handler is 'throw'
     */
    protected function checkFeatureAccess(?Model $context, bool $bypassFeatureCheck = false): bool
    {
        // Skip if feature integration is disabled
        if (! $this->featureIntegrationEnabled()) {
            return true;
        }

        // Skip if context is not a Feature
        if (! $this->isFeatureContext($context)) {
            return true;
        }

        // Skip if bypass is requested (e.g., admin override)
        if ($bypassFeatureCheck) {
            return true;
        }

        // Get the feature access handler
        $handler = $this->getFeatureAccessHandler();

        if ($handler === null) {
            return $this->handleMissingFeatureHandler();
        }

        // Check if subject can access the feature
        /** @var Model $this */
        return $handler->canAccess($context, $this);
    }

    /**
     * Get the feature access handler from the container.
     */
    protected function getFeatureAccessHandler(): ?FeatureAccessHandler
    {
        if (! app()->bound(FeatureAccessHandler::class)) {
            return null;
        }

        return app(FeatureAccessHandler::class);
    }

    /**
     * Handle the case when feature handler is not available.
     *
     * @throws FeatureAccessException If on_missing_handler is 'throw'
     */
    protected function handleMissingFeatureHandler(): bool
    {
        $behavior = config('mandate.features.on_missing_handler', 'deny');

        return match ($behavior) {
            'allow' => true,
            'throw' => throw FeatureAccessException::handlerNotAvailable(),
            default => false, // 'deny'
        };
    }
}

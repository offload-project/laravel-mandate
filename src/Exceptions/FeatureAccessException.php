<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class FeatureAccessException extends Exception
{
    public function __construct(
        string $message = 'Feature access handler is not available.',
        public readonly ?Model $feature = null,
        public readonly ?Model $subject = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception for missing handler.
     */
    public static function handlerNotAvailable(): self
    {
        return new self('Feature access handler is not available. Ensure a FeatureAccessHandler implementation is bound in the container.');
    }

    /**
     * Create exception for feature access denied.
     */
    public static function accessDenied(Model $feature, Model $subject): self
    {
        $featureClass = $feature::class;
        $featureId = $feature->getKey();
        $subjectClass = $subject::class;
        $subjectId = $subject->getKey();

        return new self(
            "Access denied to feature [{$featureClass}:{$featureId}] for subject [{$subjectClass}:{$subjectId}].",
            $feature,
            $subject,
        );
    }

    /**
     * Create exception for a Feature missing the HasEntitlementType contract.
     */
    public static function missingEntitlementType(Model $feature): self
    {
        $class = $feature::class;

        return new self(
            "Feature [{$class}] must implement OffloadProject\\Mandate\\Contracts\\HasEntitlementType to use the entitlements integration.",
            $feature,
        );
    }
}

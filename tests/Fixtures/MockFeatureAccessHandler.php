<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;

class MockFeatureAccessHandler implements FeatureAccessHandler
{
    /**
     * Features that are globally active.
     *
     * @var array<int|string, bool>
     */
    public array $activeFeatures = [];

    /**
     * Subjects that have access to specific features.
     *
     * @var array<int|string, array<int|string, bool>>
     */
    public array $subjectAccess = [];

    public function isActive(Model $feature): bool
    {
        return $this->activeFeatures[$feature->getKey()] ?? false;
    }

    public function hasAccess(Model $feature, Model $subject): bool
    {
        return $this->subjectAccess[$feature->getKey()][$subject->getKey()] ?? false;
    }

    public function canAccess(Model $feature, Model $subject): bool
    {
        return $this->isActive($feature) && $this->hasAccess($feature, $subject);
    }

    public function setFeatureActive(Model $feature, bool $active = true): self
    {
        $this->activeFeatures[$feature->getKey()] = $active;

        return $this;
    }

    public function grantAccess(Model $feature, Model $subject): self
    {
        $this->subjectAccess[$feature->getKey()][$subject->getKey()] = true;

        return $this;
    }

    public function revokeAccess(Model $feature, Model $subject): self
    {
        $this->subjectAccess[$feature->getKey()][$subject->getKey()] = false;

        return $this;
    }

    public function reset(): self
    {
        $this->activeFeatures = [];
        $this->subjectAccess = [];

        return $this;
    }
}

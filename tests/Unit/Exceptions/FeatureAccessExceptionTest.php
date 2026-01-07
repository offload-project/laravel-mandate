<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Unit\Exceptions;

use OffloadProject\Mandate\Exceptions\FeatureAccessException;
use OffloadProject\Mandate\Tests\Fixtures\Feature;
use OffloadProject\Mandate\Tests\Fixtures\User;
use OffloadProject\Mandate\Tests\TestCase;

class FeatureAccessExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableFeatureIntegration();
    }

    public function test_handler_not_available_exception(): void
    {
        $exception = FeatureAccessException::handlerNotAvailable();

        $this->assertInstanceOf(FeatureAccessException::class, $exception);
        $this->assertStringContainsString('Feature access handler is not available', $exception->getMessage());
        $this->assertNull($exception->feature);
        $this->assertNull($exception->subject);
    }

    public function test_access_denied_exception(): void
    {
        $feature = Feature::create(['name' => 'Test Feature']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $exception = FeatureAccessException::accessDenied($feature, $user);

        $this->assertInstanceOf(FeatureAccessException::class, $exception);
        $this->assertStringContainsString('Access denied', $exception->getMessage());
        $this->assertStringContainsString((string) $feature->getKey(), $exception->getMessage());
        $this->assertStringContainsString((string) $user->getKey(), $exception->getMessage());
        $this->assertSame($feature, $exception->feature);
        $this->assertSame($user, $exception->subject);
    }
}

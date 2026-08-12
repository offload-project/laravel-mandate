<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Feature\Integrations;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Exceptions\FeatureAccessException;
use OffloadProject\Mandate\Integrations\Entitlements\EntitlementsBridge;
use OffloadProject\Mandate\Integrations\Entitlements\EntitlementsFeatureAccessHandler;
use OffloadProject\Mandate\Tests\Fixtures\EntitledFeature;
use OffloadProject\Mandate\Tests\Fixtures\FakeEntitlementType;
use OffloadProject\Mandate\Tests\Fixtures\Feature;
use OffloadProject\Mandate\Tests\Fixtures\User;
use OffloadProject\Mandate\Tests\TestCase;
use UnitEnum;

class EntitlementsFeatureAccessHandlerTest extends TestCase
{
    private EntitlementsBridge $bridge;

    private EntitlementsFeatureAccessHandler $handler;

    private User $user;

    private EntitledFeature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableFeatureIntegration();

        $this->bridge = Mockery::mock(EntitlementsBridge::class);
        $this->handler = new EntitlementsFeatureAccessHandler($this->bridge);

        $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $this->feature = EntitledFeature::create(['name' => 'AI Tokens', 'is_active' => true]);
    }

    public function test_is_active_returns_true(): void
    {
        $this->assertTrue($this->handler->isActive($this->feature));
    }

    public function test_has_access_delegates_to_bridge_can(): void
    {
        $this->bridge->shouldReceive('can')
            ->once()
            ->withArgs(function (Model $subject, UnitEnum $type, int $amount) {
                return $subject->is($this->user)
                    && $type === FakeEntitlementType::AiTokens
                    && $amount === 1;
            })
            ->andReturn(true);

        $this->assertTrue($this->handler->hasAccess($this->feature, $this->user));
    }

    public function test_has_access_returns_false_when_bridge_denies(): void
    {
        $this->bridge->shouldReceive('can')->once()->andReturn(false);

        $this->assertFalse($this->handler->hasAccess($this->feature, $this->user));
    }

    public function test_has_access_throws_when_feature_missing_contract(): void
    {
        $plain = Feature::create(['name' => 'Plain Feature']);

        $this->expectException(FeatureAccessException::class);
        $this->expectExceptionMessage('must implement OffloadProject\\Mandate\\Contracts\\HasEntitlementType');

        $this->handler->hasAccess($plain, $this->user);
    }

    public function test_can_access_returns_true_only_when_bridge_allows(): void
    {
        $this->bridge->shouldReceive('can')->once()->andReturn(true);
        $this->assertTrue($this->handler->canAccess($this->feature, $this->user));

        $this->bridge->shouldReceive('can')->once()->andReturn(false);
        $this->assertFalse($this->handler->canAccess($this->feature, $this->user));
    }

    public function test_service_provider_binds_handler_when_config_enabled(): void
    {
        config(['mandate.features.entitlements.enabled' => true]);

        $this->app->forgetInstance(FeatureAccessHandler::class);
        $this->app->forgetInstance(EntitlementsBridge::class);

        $this->app->register(\OffloadProject\Mandate\MandateServiceProvider::class, force: true);

        $this->app->instance(EntitlementsBridge::class, Mockery::mock(EntitlementsBridge::class));

        $resolved = $this->app->make(FeatureAccessHandler::class);

        $this->assertInstanceOf(EntitlementsFeatureAccessHandler::class, $resolved);
    }

    public function test_service_provider_does_not_bind_handler_when_config_disabled(): void
    {
        config(['mandate.features.entitlements.enabled' => false]);

        $this->app->forgetInstance(FeatureAccessHandler::class);
        $this->app->forgetInstance(EntitlementsBridge::class);
        unset($this->app[FeatureAccessHandler::class]);
        unset($this->app[EntitlementsBridge::class]);

        $this->app->register(\OffloadProject\Mandate\MandateServiceProvider::class, force: true);

        $this->assertFalse($this->app->bound(FeatureAccessHandler::class));
        $this->assertFalse($this->app->bound(EntitlementsBridge::class));
    }
}

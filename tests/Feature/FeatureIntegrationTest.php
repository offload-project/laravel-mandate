<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Feature;

use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Facades\Mandate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\Feature;
use OffloadProject\Mandate\Tests\Fixtures\MockFeatureAccessHandler;
use OffloadProject\Mandate\Tests\Fixtures\Team;
use OffloadProject\Mandate\Tests\Fixtures\User;
use OffloadProject\Mandate\Tests\TestCase;

class FeatureIntegrationTest extends TestCase
{
    private User $user;

    private Feature $feature;

    private MockFeatureAccessHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->enableFeatureIntegration();
        $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $this->feature = Feature::create(['name' => 'Test Feature', 'is_active' => true]);
    }

    // =========================================================================
    // Feature Integration Configuration Tests
    // =========================================================================

    public function test_feature_integration_disabled_by_default(): void
    {
        config(['mandate.features.enabled' => false]);

        $this->assertFalse(Mandate::featureIntegrationEnabled());
    }

    public function test_feature_integration_requires_context_enabled(): void
    {
        config(['mandate.features.enabled' => true]);
        config(['mandate.context.enabled' => false]);

        $this->assertFalse(Mandate::featureIntegrationEnabled());
    }

    public function test_feature_integration_enabled_when_both_configs_true(): void
    {
        $this->assertTrue(Mandate::featureIntegrationEnabled());
    }

    // =========================================================================
    // Feature Context Detection Tests
    // =========================================================================

    public function test_is_feature_context_returns_true_for_feature_model(): void
    {
        $this->assertTrue(Mandate::isFeatureContext($this->feature));
    }

    public function test_is_feature_context_returns_false_for_non_feature_model(): void
    {
        $team = Team::create(['name' => 'Test Team']);

        $this->assertFalse(Mandate::isFeatureContext($team));
    }

    public function test_is_feature_context_returns_false_for_null(): void
    {
        $this->assertFalse(Mandate::isFeatureContext(null));
    }

    // =========================================================================
    // Feature Access Handler Tests
    // =========================================================================

    public function test_get_feature_access_handler_returns_bound_handler(): void
    {
        $handler = Mandate::getFeatureAccessHandler();

        $this->assertInstanceOf(FeatureAccessHandler::class, $handler);
        $this->assertSame($this->handler, $handler);
    }

    public function test_get_feature_access_handler_returns_null_when_not_bound(): void
    {
        $this->app->forgetInstance(FeatureAccessHandler::class);

        $handler = Mandate::getFeatureAccessHandler();

        $this->assertNull($handler);
    }

    // =========================================================================
    // Feature Active Checks Tests
    // =========================================================================

    public function test_is_feature_active_delegates_to_handler(): void
    {
        $this->handler->setFeatureActive($this->feature, true);

        $this->assertTrue(Mandate::isFeatureActive($this->feature));

        $this->handler->setFeatureActive($this->feature, false);

        $this->assertFalse(Mandate::isFeatureActive($this->feature));
    }

    public function test_has_feature_access_delegates_to_handler(): void
    {
        $this->handler->grantAccess($this->feature, $this->user);

        $this->assertTrue(Mandate::hasFeatureAccess($this->feature, $this->user));

        $this->handler->revokeAccess($this->feature, $this->user);

        $this->assertFalse(Mandate::hasFeatureAccess($this->feature, $this->user));
    }

    public function test_can_access_feature_requires_both_active_and_access(): void
    {
        // Neither active nor access
        $this->assertFalse(Mandate::canAccessFeature($this->feature, $this->user));

        // Only active
        $this->handler->setFeatureActive($this->feature, true);
        $this->assertFalse(Mandate::canAccessFeature($this->feature, $this->user));

        // Only access
        $this->handler->setFeatureActive($this->feature, false);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertFalse(Mandate::canAccessFeature($this->feature, $this->user));

        // Both active and access
        $this->handler->setFeatureActive($this->feature, true);
        $this->assertTrue(Mandate::canAccessFeature($this->feature, $this->user));
    }

    // =========================================================================
    // Missing Handler Behavior Tests
    // =========================================================================

    public function test_missing_handler_deny_behavior(): void
    {
        $this->app->forgetInstance(FeatureAccessHandler::class);
        $this->setFeatureMissingHandlerBehavior('deny');

        $this->assertFalse(Mandate::isFeatureActive($this->feature));
        $this->assertFalse(Mandate::hasFeatureAccess($this->feature, $this->user));
        $this->assertFalse(Mandate::canAccessFeature($this->feature, $this->user));
    }

    public function test_missing_handler_allow_behavior(): void
    {
        $this->app->forgetInstance(FeatureAccessHandler::class);
        $this->setFeatureMissingHandlerBehavior('allow');

        $this->assertTrue(Mandate::isFeatureActive($this->feature));
        $this->assertTrue(Mandate::hasFeatureAccess($this->feature, $this->user));
        $this->assertTrue(Mandate::canAccessFeature($this->feature, $this->user));
    }

    // =========================================================================
    // Permission Checks with Feature Context Tests
    // =========================================================================

    public function test_has_permission_checks_feature_access_first(): void
    {
        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission, $this->feature);

        // Feature not accessible - permission check should fail
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasPermission('edit', $this->feature));

        // Feature accessible - permission check should pass
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasPermission('edit', $this->feature));
    }

    public function test_has_permission_bypass_feature_check(): void
    {
        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);

        // Without bypass - should fail
        $this->assertFalse($this->user->hasPermission('edit', $this->feature));

        // With bypass - should pass
        $this->assertTrue($this->user->hasPermission('edit', $this->feature, bypassFeatureCheck: true));
    }

    public function test_has_any_permission_checks_feature_access(): void
    {
        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasAnyPermission(['edit', 'delete'], $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasAnyPermission(['edit', 'delete'], $this->feature));
    }

    public function test_has_all_permissions_checks_feature_access(): void
    {
        $edit = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $delete = Permission::create(['name' => 'delete', 'guard' => 'web']);
        $this->user->grantPermission([$edit, $delete], $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasAllPermissions(['edit', 'delete'], $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasAllPermissions(['edit', 'delete'], $this->feature));
    }

    // =========================================================================
    // Role Checks with Feature Context Tests
    // =========================================================================

    public function test_has_role_checks_feature_access_first(): void
    {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasRole('editor', $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasRole('editor', $this->feature));
    }

    public function test_has_role_bypass_feature_check(): void
    {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);

        // Without bypass - should fail
        $this->assertFalse($this->user->hasRole('editor', $this->feature));

        // With bypass - should pass
        $this->assertTrue($this->user->hasRole('editor', $this->feature, bypassFeatureCheck: true));
    }

    public function test_has_any_role_checks_feature_access(): void
    {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasAnyRole(['editor', 'admin'], $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasAnyRole(['editor', 'admin'], $this->feature));
    }

    public function test_has_all_roles_checks_feature_access(): void
    {
        $editor = Role::create(['name' => 'editor', 'guard' => 'web']);
        $reviewer = Role::create(['name' => 'reviewer', 'guard' => 'web']);
        $this->user->assignRole([$editor, $reviewer], $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasAllRoles(['editor', 'reviewer'], $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasAllRoles(['editor', 'reviewer'], $this->feature));
    }

    public function test_has_exact_roles_checks_feature_access(): void
    {
        $editor = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($editor, $this->feature);

        // Feature not accessible
        $this->handler->setFeatureActive($this->feature, false);
        $this->assertFalse($this->user->hasExactRoles(['editor'], $this->feature));

        // Feature accessible
        $this->handler->setFeatureActive($this->feature, true);
        $this->handler->grantAccess($this->feature, $this->user);
        $this->assertTrue($this->user->hasExactRoles(['editor'], $this->feature));
    }

    // =========================================================================
    // Non-Feature Context Tests
    // =========================================================================

    public function test_permission_check_passes_for_non_feature_context(): void
    {
        $team = Team::create(['name' => 'Test Team']);
        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission, $team);

        // Team is not a Feature model - should pass without feature check
        $this->assertTrue($this->user->hasPermission('edit', $team));
    }

    public function test_role_check_passes_for_non_feature_context(): void
    {
        $team = Team::create(['name' => 'Test Team']);
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role, $team);

        // Team is not a Feature model - should pass without feature check
        $this->assertTrue($this->user->hasRole('editor', $team));
    }

    public function test_permission_check_passes_without_context(): void
    {
        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission);

        // No context - should pass without feature check
        $this->assertTrue($this->user->hasPermission('edit'));
    }

    public function test_role_check_passes_without_context(): void
    {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role);

        // No context - should pass without feature check
        $this->assertTrue($this->user->hasRole('editor'));
    }

    // =========================================================================
    // Feature Integration Disabled Tests
    // =========================================================================

    public function test_permission_check_passes_when_feature_integration_disabled(): void
    {
        config(['mandate.features.enabled' => false]);

        $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
        $this->user->grantPermission($permission, $this->feature);

        // Feature integration disabled - should pass without feature check
        $this->assertTrue($this->user->hasPermission('edit', $this->feature));
    }

    public function test_role_check_passes_when_feature_integration_disabled(): void
    {
        config(['mandate.features.enabled' => false]);

        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole($role, $this->feature);

        // Feature integration disabled - should pass without feature check
        $this->assertTrue($this->user->hasRole('editor', $this->feature));
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('Blade Directives', function () {
    it('registers @role directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('role')
            ->and($directives)->toHaveKey('endrole');
    });

    it('registers @hasrole directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasrole')
            ->and($directives)->toHaveKey('endhasrole');
    });

    it('registers @permission directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('permission')
            ->and($directives)->toHaveKey('endpermission');
    });

    it('registers @unlessrole directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('unlessrole')
            ->and($directives)->toHaveKey('endunlessrole');
    });

    it('registers @unlesspermission directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('unlesspermission')
            ->and($directives)->toHaveKey('endunlesspermission');
    });

    it('registers @hasanyrole directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasanyrole')
            ->and($directives)->toHaveKey('endhasanyrole');
    });

    it('registers @hasallroles directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasallroles')
            ->and($directives)->toHaveKey('endhasallroles');
    });

    it('registers @hasexactroles directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasexactroles')
            ->and($directives)->toHaveKey('endhasexactroles');
    });

    it('registers @hasanypermission directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasanypermission')
            ->and($directives)->toHaveKey('endhasanypermission');
    });

    it('registers @hasallpermissions directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasallpermissions')
            ->and($directives)->toHaveKey('endhasallpermissions');
    });
});

describe('Blade Directives Runtime', function () {
    it('@role shows content when user has role', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        $this->user->assignRole('admin');
        $this->actingAs($this->user);

        $view = $this->blade('@role("admin") Admin Content @endrole');

        $view->assertSee('Admin Content');
    });

    it('@role hides content when user lacks role', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        $this->actingAs($this->user);

        $view = $this->blade('@role("admin") Admin Content @endrole');

        $view->assertDontSee('Admin Content');
    });

    it('@unlessrole shows content when user lacks role', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        $this->actingAs($this->user);

        $view = $this->blade('@unlessrole("admin") Not Admin @endunlessrole');

        $view->assertSee('Not Admin');
    });

    it('@permission shows content when user has permission', function () {
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $this->user->grantPermission('article:edit');
        $this->actingAs($this->user);

        $view = $this->blade('@permission("article:edit") Can Edit @endpermission');

        $view->assertSee('Can Edit');
    });

    it('@permission hides content when user lacks permission', function () {
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $this->actingAs($this->user);

        $view = $this->blade('@permission("article:edit") Can Edit @endpermission');

        $view->assertDontSee('Can Edit');
    });

    it('@hasanyrole works with pipe-separated roles', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRole('editor');
        $this->actingAs($this->user);

        $view = $this->blade('@hasanyrole("admin|editor") Has Role @endhasanyrole');

        $view->assertSee('Has Role');
    });

    it('@hasallroles requires all roles', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);
        $this->user->assignRoles(['admin', 'editor']);
        $this->actingAs($this->user);

        $view = $this->blade('@hasallroles("admin|editor") Has All @endhasallroles');

        $view->assertSee('Has All');
    });
});

describe('Guest Behavior', function () {
    it('hides role content for guests', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $view = $this->blade('@role("admin") Admin @endrole');

        $view->assertDontSee('Admin');
    });

    it('shows unlessrole content for guests', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $view = $this->blade('@unlessrole("admin") Guest @endunlessrole');

        $view->assertSee('Guest');
    });
});

describe('Capability Blade Directives Registration', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    it('registers @capability directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('capability')
            ->and($directives)->toHaveKey('endcapability');
    });

    it('registers @hascapability directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hascapability')
            ->and($directives)->toHaveKey('endhascapability');
    });

    it('registers @hasanycapability directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasanycapability')
            ->and($directives)->toHaveKey('endhasanycapability');
    });

    it('registers @hasallcapabilities directive', function () {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('hasallcapabilities')
            ->and($directives)->toHaveKey('endhasallcapabilities');
    });
});

describe('Capability Blade Directives Runtime', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    it('@capability shows content when user has capability via role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $role->assignCapability('manage-posts');
        $this->user->assignRole('editor');
        $this->actingAs($this->user);

        $view = $this->blade('@capability("manage-posts") Can Manage Posts @endcapability');

        $view->assertSee('Can Manage Posts');
    });

    it('@capability hides content when user lacks capability', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $this->actingAs($this->user);

        $view = $this->blade('@capability("manage-posts") Can Manage Posts @endcapability');

        $view->assertDontSee('Can Manage Posts');
    });

    it('@hascapability works as alias for @capability', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $role->assignCapability('manage-posts');
        $this->user->assignRole('editor');
        $this->actingAs($this->user);

        $view = $this->blade('@hascapability("manage-posts") Has Capability @endhascapability');

        $view->assertSee('Has Capability');
    });

    it('@hasanycapability shows content when user has any capability', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);
        $role->assignCapability('manage-posts');
        $this->user->assignRole('editor');
        $this->actingAs($this->user);

        $view = $this->blade('@hasanycapability("manage-posts|manage-users") Has Any @endhasanycapability');

        $view->assertSee('Has Any');
    });

    it('@hasanycapability hides content when user has none of the capabilities', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);
        $this->actingAs($this->user);

        $view = $this->blade('@hasanycapability("manage-posts|manage-users") Has Any @endhasanycapability');

        $view->assertDontSee('Has Any');
    });

    it('@hasallcapabilities shows content when user has all capabilities', function () {
        $role = Role::create(['name' => 'admin', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);
        $role->assignCapability(['manage-posts', 'manage-users']);
        $this->user->assignRole('admin');
        $this->actingAs($this->user);

        $view = $this->blade('@hasallcapabilities("manage-posts|manage-users") Has All @endhasallcapabilities');

        $view->assertSee('Has All');
    });

    it('@hasallcapabilities hides content when user lacks some capabilities', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);
        $role->assignCapability('manage-posts');
        $this->user->assignRole('editor');
        $this->actingAs($this->user);

        $view = $this->blade('@hasallcapabilities("manage-posts|manage-users") Has All @endhasallcapabilities');

        $view->assertDontSee('Has All');
    });
});

describe('Capability Blade Directives with Direct Assignment', function () {
    beforeEach(function () {
        $this->enableCapabilities();
        $this->enableDirectCapabilityAssignment();
    });

    it('@capability shows content with direct capability assignment', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $this->user->assignCapability('manage-posts');
        $this->actingAs($this->user);

        $view = $this->blade('@capability("manage-posts") Direct Capability @endcapability');

        $view->assertSee('Direct Capability');
    });
});

describe('Capability Blade Directives when Disabled', function () {
    it('@capability hides content when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);
        $this->actingAs($this->user);

        $view = $this->blade('@capability("manage-posts") Should Not Show @endcapability');

        $view->assertDontSee('Should Not Show');
    });

    it('@hasanycapability hides content when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);
        $this->actingAs($this->user);

        $view = $this->blade('@hasanycapability("manage-posts") Should Not Show @endhasanycapability');

        $view->assertDontSee('Should Not Show');
    });
});

describe('Capability Blade Directives Guest Behavior', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    it('hides capability content for guests', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $view = $this->blade('@capability("manage-posts") Capability Content @endcapability');

        $view->assertDontSee('Capability Content');
    });

    it('hides hasanycapability content for guests', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $view = $this->blade('@hasanycapability("manage-posts") Any Capability @endhasanycapability');

        $view->assertDontSee('Any Capability');
    });
});

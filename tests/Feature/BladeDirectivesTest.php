<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
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

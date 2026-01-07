<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Role;

beforeEach(function () {
    $this->enableCapabilities();
});

describe('AssignCapabilityCommand', function () {
    it('assigns a capability to a role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->artisan('mandate:assign-capability', [
            'role' => 'editor',
            'capability' => 'manage-posts',
        ])->assertSuccessful()
            ->expectsOutputToContain("Capability 'manage-posts' assigned to role 'editor'");

        $role->refresh();
        expect($role->capabilities)->toHaveCount(1);
    });

    it('assigns a capability with specified guard', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'api']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'api']);

        $this->artisan('mandate:assign-capability', [
            'role' => 'editor',
            'capability' => 'manage-posts',
            '--guard' => 'api',
        ])->assertSuccessful();

        $role->refresh();
        expect($role->capabilities)->toHaveCount(1);
    });

    it('fails when role not found', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->artisan('mandate:assign-capability', [
            'role' => 'nonexistent',
            'capability' => 'manage-posts',
        ])->assertFailed()
            ->expectsOutputToContain("Role 'nonexistent' not found");
    });

    it('fails when capability not found', function () {
        Role::create(['name' => 'editor', 'guard' => 'web']);

        $this->artisan('mandate:assign-capability', [
            'role' => 'editor',
            'capability' => 'nonexistent',
        ])->assertFailed()
            ->expectsOutputToContain("Capability 'nonexistent' not found");
    });

    it('warns when role already has capability', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $role->assignCapability($capability);

        $this->artisan('mandate:assign-capability', [
            'role' => 'editor',
            'capability' => 'manage-posts',
        ])->assertSuccessful()
            ->expectsOutputToContain('already has capability');
    });

    it('fails when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);

        $this->artisan('mandate:assign-capability', [
            'role' => 'editor',
            'capability' => 'manage-posts',
        ])->assertFailed()
            ->expectsOutputToContain('not enabled');
    });
});

<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Capability;

beforeEach(function () {
    $this->enableCapabilities();
});

describe('CreateCapabilityCommand', function () {
    it('creates a capability', function () {
        $this->artisan('mandate:capability', ['name' => 'manage-posts'])
            ->assertSuccessful()
            ->expectsOutputToContain("Capability 'manage-posts' created");

        expect(Capability::where('name', 'manage-posts')->exists())->toBeTrue();
    });

    it('creates a capability with specified guard', function () {
        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--guard' => 'api',
        ])->assertSuccessful();

        $capability = Capability::where('name', 'manage-posts')->first();

        expect($capability->guard)->toBe('api');
    });

    it('creates a capability with permissions', function () {
        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--permissions' => 'posts:view,posts:edit,posts:delete',
        ])->assertSuccessful()
            ->expectsOutputToContain('Assigned 3 permission(s)');

        $capability = Capability::where('name', 'manage-posts')->first();

        expect($capability->permissions)->toHaveCount(3);
    });

    it('warns when capability already exists', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->artisan('mandate:capability', ['name' => 'manage-posts'])
            ->assertSuccessful()
            ->expectsOutputToContain('already exists');
    });

    it('fails when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);

        $this->artisan('mandate:capability', ['name' => 'manage-posts'])
            ->assertFailed()
            ->expectsOutputToContain('not enabled');
    });
});

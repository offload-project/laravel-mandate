<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Capability;

describe('mandate:capability --db', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    it('creates a capability in database', function () {
        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--db' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain("Capability 'manage-posts' created");

        expect(Capability::where('name', 'manage-posts')->exists())->toBeTrue();
    });

    it('creates a capability with specified guard', function () {
        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--guard' => 'api',
            '--db' => true,
        ])->assertSuccessful();

        $capability = Capability::where('name', 'manage-posts')->first();

        expect($capability->guard)->toBe('api');
    });

    it('creates a capability with permissions', function () {
        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--permissions' => 'post:view,post:edit,post:delete',
            '--db' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Assigned 3 permission(s)');

        $capability = Capability::where('name', 'manage-posts')->first();

        expect($capability->permissions)->toHaveCount(3);
    });

    it('warns when capability already exists', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--db' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('already exists');
    });
});

describe('mandate:capability --db when capabilities disabled', function () {
    it('fails when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);

        $this->artisan('mandate:capability', [
            'name' => 'manage-posts',
            '--db' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('not enabled');
    });
});

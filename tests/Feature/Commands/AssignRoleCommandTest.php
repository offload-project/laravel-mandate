<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

describe('AssignRoleCommand', function () {
    it('assigns a role to a subject', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->artisan('mandate:assign-role', [
            'role' => 'admin',
            'subject' => $user->id,
        ])
            ->expectsOutputToContain('assigned')
            ->assertSuccessful();

        expect($user->fresh()->hasRole('admin'))->toBeTrue();
    });

    it('assigns a role with explicit model class', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'editor', 'guard' => 'web']);

        $this->artisan('mandate:assign-role', [
            'role' => 'editor',
            'subject' => $user->id,
            '--model' => User::class,
        ])
            ->assertSuccessful();

        expect($user->fresh()->hasRole('editor'))->toBeTrue();
    });

    it('fails when subject not found', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->artisan('mandate:assign-role', [
            'role' => 'admin',
            'subject' => 9999,
        ])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    });

    it('fails when role not found', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->artisan('mandate:assign-role', [
            'role' => 'nonexistent',
            'subject' => $user->id,
        ])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    });
});

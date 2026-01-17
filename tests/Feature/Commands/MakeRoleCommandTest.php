<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

describe('MakeRoleCommand', function () {
    beforeEach(function () {
        // Clean up any previously generated files
        $path = app_path('Roles/TestRoles.php');
        if (File::exists($path)) {
            File::delete($path);
        }
    });

    afterEach(function () {
        // Clean up generated files
        $path = app_path('Roles/TestRoles.php');
        if (File::exists($path)) {
            File::delete($path);
        }
        // Remove the directory if empty
        $dir = app_path('Roles');
        if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    });

    it('generates a role class file', function () {
        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        expect(File::exists(app_path('Roles/TestRoles.php')))->toBeTrue();
    });

    it('includes guard attribute', function () {
        $this->artisan('mandate:role', [
            'name' => 'TestRoles',
            '--guard' => 'api',
        ])
            ->assertSuccessful();

        $content = File::get(app_path('Roles/TestRoles.php'));
        expect($content)->toContain("#[Guard('api')]");
    });

    it('includes role constants', function () {
        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        $content = File::get(app_path('Roles/TestRoles.php'));
        expect($content)->toContain('const ADMIN');
        expect($content)->toContain('const USER');
        expect($content)->toContain('const GUEST');
    });

    it('does not overwrite existing file without force flag', function () {
        // Create the file first
        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        // Try to create again without force
        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertFailed();
    });

    it('overwrites existing file with force flag', function () {
        // Create the file first
        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        // Try to create again with force
        $this->artisan('mandate:role', [
            'name' => 'TestRoles',
            '--force' => true,
        ])
            ->assertSuccessful();

        expect(File::exists(app_path('Roles/TestRoles.php')))->toBeTrue();
    });
})->skip(fn () => ! class_exists(Illuminate\Console\GeneratorCommand::class), 'GeneratorCommand not available');

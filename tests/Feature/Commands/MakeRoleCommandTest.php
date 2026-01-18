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

    it('generates in custom configured path', function () {
        $customPath = app_path('Authorization/Roles');

        // Set custom path in config
        config(['mandate.code_first.paths.roles' => $customPath]);

        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        expect(File::exists($customPath.'/TestRoles.php'))->toBeTrue();

        $content = File::get($customPath.'/TestRoles.php');
        expect($content)->toContain('namespace App\\Authorization\\Roles;');

        // Clean up
        File::delete($customPath.'/TestRoles.php');
        if (File::isDirectory($customPath) && count(File::files($customPath)) === 0) {
            File::deleteDirectory($customPath);
        }
        $authDir = app_path('Authorization');
        if (File::isDirectory($authDir) && count(File::allFiles($authDir)) === 0) {
            File::deleteDirectory($authDir);
        }
    });

    it('generates correct namespace for nested custom path', function () {
        $customPath = app_path('Domain/Auth/Roles');

        config(['mandate.code_first.paths.roles' => $customPath]);

        $this->artisan('mandate:role', ['name' => 'TestRoles'])
            ->assertSuccessful();

        expect(File::exists($customPath.'/TestRoles.php'))->toBeTrue();

        $content = File::get($customPath.'/TestRoles.php');
        expect($content)->toContain('namespace App\\Domain\\Auth\\Roles;');

        // Clean up
        File::delete($customPath.'/TestRoles.php');
        File::deleteDirectory(app_path('Domain/Auth/Roles'));
        File::deleteDirectory(app_path('Domain/Auth'));
        File::deleteDirectory(app_path('Domain'));
    });
})->skip(fn () => ! class_exists(Illuminate\Console\GeneratorCommand::class), 'GeneratorCommand not available');

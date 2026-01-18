<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

describe('MakePermissionCommand', function () {
    beforeEach(function () {
        // Clean up any previously generated files
        $path = app_path('Permissions/TestPermissions.php');
        if (File::exists($path)) {
            File::delete($path);
        }
    });

    afterEach(function () {
        // Clean up generated files
        $path = app_path('Permissions/TestPermissions.php');
        if (File::exists($path)) {
            File::delete($path);
        }
        // Remove the directory if empty
        $dir = app_path('Permissions');
        if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    });

    it('generates a permission class file', function () {
        $this->artisan('mandate:permission', ['name' => 'TestPermissions'])
            ->assertSuccessful();

        expect(File::exists(app_path('Permissions/TestPermissions.php')))->toBeTrue();
    });

    it('includes guard attribute', function () {
        $this->artisan('mandate:permission', [
            'name' => 'TestPermissions',
            '--guard' => 'api',
        ])
            ->assertSuccessful();

        $content = File::get(app_path('Permissions/TestPermissions.php'));
        expect($content)->toContain("#[Guard('api')]");
    });

    it('includes CRUD constants', function () {
        $this->artisan('mandate:permission', ['name' => 'TestPermissions'])
            ->assertSuccessful();

        $content = File::get(app_path('Permissions/TestPermissions.php'));
        expect($content)->toContain('const VIEW');
        expect($content)->toContain('const CREATE');
        expect($content)->toContain('const UPDATE');
        expect($content)->toContain('const DELETE');
    });

    it('generates in custom configured path', function () {
        $customPath = app_path('Authorization/Permissions');

        // Set custom path in config
        config(['mandate.code_first.paths.permissions' => $customPath]);

        $this->artisan('mandate:permission', ['name' => 'TestPermissions'])
            ->assertSuccessful();

        expect(File::exists($customPath.'/TestPermissions.php'))->toBeTrue();

        $content = File::get($customPath.'/TestPermissions.php');
        expect($content)->toContain('namespace App\\Authorization\\Permissions;');

        // Clean up
        File::delete($customPath.'/TestPermissions.php');
        if (File::isDirectory($customPath) && count(File::files($customPath)) === 0) {
            File::deleteDirectory($customPath);
        }
        $authDir = app_path('Authorization');
        if (File::isDirectory($authDir) && count(File::allFiles($authDir)) === 0) {
            File::deleteDirectory($authDir);
        }
    });

    it('generates correct namespace for nested custom path', function () {
        $customPath = app_path('Domain/Auth/Permissions');

        config(['mandate.code_first.paths.permissions' => $customPath]);

        $this->artisan('mandate:permission', ['name' => 'TestPermissions'])
            ->assertSuccessful();

        expect(File::exists($customPath.'/TestPermissions.php'))->toBeTrue();

        $content = File::get($customPath.'/TestPermissions.php');
        expect($content)->toContain('namespace App\\Domain\\Auth\\Permissions;');

        // Clean up
        File::delete($customPath.'/TestPermissions.php');
        File::deleteDirectory(app_path('Domain/Auth/Permissions'));
        File::deleteDirectory(app_path('Domain/Auth'));
        File::deleteDirectory(app_path('Domain'));
    });
})->skip(fn () => ! class_exists(Illuminate\Console\GeneratorCommand::class), 'GeneratorCommand not available');

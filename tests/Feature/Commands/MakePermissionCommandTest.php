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
})->skip(fn () => ! class_exists(Illuminate\Console\GeneratorCommand::class), 'GeneratorCommand not available');

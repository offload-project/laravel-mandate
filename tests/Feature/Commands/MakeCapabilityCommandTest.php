<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

describe('MakeCapabilityCommand', function () {
    beforeEach(function () {
        // Clean up any previously generated files
        $path = app_path('Capabilities/TestCapabilities.php');
        if (File::exists($path)) {
            File::delete($path);
        }
    });

    afterEach(function () {
        // Clean up generated files
        $path = app_path('Capabilities/TestCapabilities.php');
        if (File::exists($path)) {
            File::delete($path);
        }
        // Remove the directory if empty
        $dir = app_path('Capabilities');
        if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    });

    it('generates a capability class file', function () {
        $this->artisan('mandate:capability', ['name' => 'TestCapabilities'])
            ->assertSuccessful();

        expect(File::exists(app_path('Capabilities/TestCapabilities.php')))->toBeTrue();
    });

    it('includes guard attribute', function () {
        $this->artisan('mandate:capability', [
            'name' => 'TestCapabilities',
            '--guard' => 'api',
        ])
            ->assertSuccessful();

        $content = File::get(app_path('Capabilities/TestCapabilities.php'));
        expect($content)->toContain("#[Guard('api')]");
    });

    it('includes capability constant', function () {
        $this->artisan('mandate:capability', ['name' => 'TestCapabilities'])
            ->assertSuccessful();

        $content = File::get(app_path('Capabilities/TestCapabilities.php'));
        expect($content)->toContain('const MANAGE');
    });

    it('does not overwrite existing file without force flag', function () {
        // Create the file first
        $this->artisan('mandate:capability', ['name' => 'TestCapabilities'])
            ->assertSuccessful();

        // Try to create again without force
        $this->artisan('mandate:capability', ['name' => 'TestCapabilities'])
            ->assertFailed();
    });

    it('overwrites existing file with force flag', function () {
        // Create the file first
        $this->artisan('mandate:capability', ['name' => 'TestCapabilities'])
            ->assertSuccessful();

        // Try to create again with force
        $this->artisan('mandate:capability', [
            'name' => 'TestCapabilities',
            '--force' => true,
        ])
            ->assertSuccessful();

        expect(File::exists(app_path('Capabilities/TestCapabilities.php')))->toBeTrue();
    });
})->skip(fn () => ! class_exists(Illuminate\Console\GeneratorCommand::class), 'GeneratorCommand not available');

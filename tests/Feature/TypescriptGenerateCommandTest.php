<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use OffloadProject\Mandate\Services\MandateManager;

beforeEach(function () {
    app(MandateManager::class)->clearCache();
});

afterEach(function () {
    // Clean up any generated test files
    $testPath = sys_get_temp_dir().'/mandate-test-typescript';
    if (is_dir($testPath)) {
        File::deleteDirectory($testPath);
    }
});

test('mandate:typescript command generates file at configured path', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/permissions.ts';

    config()->set('mandate.typescript_path', $outputPath);

    $this->artisan('mandate:typescript')
        ->assertSuccessful()
        ->expectsOutputToContain('TypeScript file generated');

    expect(file_exists($outputPath))->toBeTrue();

    $content = file_get_contents($outputPath);
    expect($content)->toContain('export const UserPermissions');
    expect($content)->toContain('export const SystemRoles');
    expect($content)->toContain('export const Features');
});

test('mandate:typescript command generates file at custom output path', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/custom/auth.ts';

    $this->artisan('mandate:typescript', ['--output' => $outputPath])
        ->assertSuccessful()
        ->expectsOutputToContain('TypeScript file generated');

    expect(file_exists($outputPath))->toBeTrue();
});

test('mandate:typescript command creates directory if it does not exist', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/nested/deep/permissions.ts';

    $this->artisan('mandate:typescript', ['--output' => $outputPath])
        ->assertSuccessful();

    expect(file_exists($outputPath))->toBeTrue();
});

test('mandate:typescript command fails when no path configured', function () {
    config()->set('mandate.typescript_path', null);

    $this->artisan('mandate:typescript')
        ->assertFailed()
        ->expectsOutputToContain('No output path configured');
});

test('mandate:typescript command output flag overrides config', function () {
    $configPath = sys_get_temp_dir().'/mandate-test-typescript/config-path.ts';
    $customPath = sys_get_temp_dir().'/mandate-test-typescript/custom-path.ts';

    config()->set('mandate.typescript_path', $configPath);

    $this->artisan('mandate:typescript', ['--output' => $customPath])
        ->assertSuccessful();

    expect(file_exists($customPath))->toBeTrue();
    expect(file_exists($configPath))->toBeFalse();
});

test('mandate:typescript generates permissions with correct constant names', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/permissions.ts';

    $this->artisan('mandate:typescript', ['--output' => $outputPath])
        ->assertSuccessful();

    $content = file_get_contents($outputPath);

    // Verify permission constants use SCREAMING_SNAKE_CASE keys
    expect($content)->toContain('VIEW: "view users"');
    expect($content)->toContain('CREATE: "create users"');
    expect($content)->toContain('UPDATE: "update users"');
    expect($content)->toContain('DELETE: "delete users"');
    expect($content)->toContain('EXPORT: "export users"');
});

test('mandate:typescript generates roles with correct constant names', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/permissions.ts';

    $this->artisan('mandate:typescript', ['--output' => $outputPath])
        ->assertSuccessful();

    $content = file_get_contents($outputPath);

    // Verify role constants
    expect($content)->toContain('ADMIN: "admin"');
    expect($content)->toContain('EDITOR: "editor"');
    expect($content)->toContain('VIEWER: "viewer"');
});

test('mandate:typescript generates features with class names as keys', function () {
    $outputPath = sys_get_temp_dir().'/mandate-test-typescript/permissions.ts';

    $this->artisan('mandate:typescript', ['--output' => $outputPath])
        ->assertSuccessful();

    $content = file_get_contents($outputPath);

    // Verify feature uses class name as key and feature name as value
    expect($content)->toContain('ExportFeature: "export"');
});

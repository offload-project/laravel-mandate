<?php

declare(strict_types=1);

describe('TypeScriptCommand', function () {
    beforeEach(function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../../Fixtures/CodeFirst']);

        $this->outputPath = sys_get_temp_dir().'/mandate-tests';
        if (! is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    });

    afterEach(function () {
        @unlink($this->outputPath.'/mandate.ts');
    });

    it('works without code-first using database records', function () {
        config(['mandate.code_first.enabled' => false]);

        // Create some database records
        OffloadProject\Mandate\Models\Permission::create(['name' => 'posts:create']);
        OffloadProject\Mandate\Models\Role::create(['name' => 'editor']);

        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);
        expect($content)->toContain('PostsPermissions');
        expect($content)->toContain('CREATE:');
        expect($content)->toContain('"posts:create"');
        expect($content)->toContain('Roles');
        expect($content)->toContain('EDITOR:');
        expect($content)->toContain('"editor"');
    });

    it('generates typescript file', function () {
        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile])
            ->assertSuccessful();

        expect(file_exists($outputFile))->toBeTrue();
    });

    it('includes permission constants', function () {
        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile, '--permissions' => true])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);
        expect($content)->toContain('ArticlePermissions');
        expect($content)->toContain('VIEW:');
        expect($content)->toContain('"article:view"');
    });

    it('includes role constants', function () {
        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile, '--roles' => true])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);
        expect($content)->toContain('SystemRoles');
        expect($content)->toContain('ADMIN:');
        expect($content)->toContain('"admin"');
    });

    it('generates union types', function () {
        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);
        expect($content)->toContain('export type Permission');
        expect($content)->toContain('export type Role');
    });

    it('sanitizes wildcard permissions to valid TypeScript identifiers', function () {
        config(['mandate.code_first.enabled' => false]);

        // Create wildcard permissions in the database
        OffloadProject\Mandate\Models\Permission::create(['name' => '*']);
        OffloadProject\Mandate\Models\Permission::create(['name' => 'article:*']);

        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);

        // Wildcard should be sanitized to WILDCARD
        expect($content)->toContain('WILDCARD: "*"');
        expect($content)->toContain('WILDCARD: "article:*"');

        // Should NOT contain bare * as a key
        expect($content)->not->toMatch('/^\s+\*:/m');
    });

    it('uses as const for objects', function () {
        $outputFile = $this->outputPath.'/mandate.ts';

        $this->artisan('mandate:typescript', ['--output' => $outputFile])
            ->assertSuccessful();

        $content = file_get_contents($outputFile);
        expect($content)->toContain('} as const;');
    });
})->skip(fn () => ! class_exists(OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer::class), 'Code-first not implemented');

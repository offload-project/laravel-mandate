<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use OffloadProject\Mandate\Services\TypescriptGenerator;

final class TypescriptGenerateCommand extends Command
{
    protected $signature = 'mandate:typescript
        {--output= : Override the output path from config}';

    protected $description = 'Generate TypeScript file with permission and role constants';

    public function handle(TypescriptGenerator $generator): int
    {
        /** @var string|null $output */
        $output = $this->option('output');
        $path = $output ?? config('mandate.typescript_path');

        if ($path === null) {
            $this->components->error('No output path configured. Set mandate.typescript_path in config or use --output option.');

            return self::FAILURE;
        }

        $content = $generator->generate();

        // Ensure directory exists
        $directory = dirname($path);
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, $content);

        $this->components->info("TypeScript file generated: {$path}");

        return self::SUCCESS;
    }
}

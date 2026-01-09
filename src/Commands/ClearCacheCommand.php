<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * Artisan command to clear the permission cache.
 *
 * Usage:
 * - php artisan mandate:cache-clear
 */
final class ClearCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:cache-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the Mandate permission cache';

    /**
     * Execute the console command.
     */
    public function handle(MandateRegistrar $registrar): int
    {
        if ($registrar->forgetCachedPermissions()) {
            $this->components->info('Permission cache cleared successfully.');
        } else {
            $this->components->info('Permission cache was already empty.');
        }

        return self::SUCCESS;
    }
}

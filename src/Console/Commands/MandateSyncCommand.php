<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Console\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Services\MandateManager;

final class MandateSyncCommand extends Command
{
    protected $signature = 'mandate:sync
        {--guard= : The guard to use for permissions and roles}
        {--permissions : Only sync permissions}
        {--roles : Only sync roles}
        {--features : Only sync features}
        {--seed : Seed role permissions from config (first-time setup)}';

    protected $description = 'Sync discovered permissions, roles, and features to the database. By default, syncs all. Use --seed for initial setup to assign permissions from config.';

    public function handle(MandateManager $mandate): int
    {
        /** @var string|null $guard */
        $guard = $this->option('guard');
        $onlyPermissions = $this->option('permissions');
        $onlyRoles = $this->option('roles');
        $onlyFeatures = $this->option('features');
        $seed = (bool) $this->option('seed');
        $hasSyncColumns = $this->hasSyncColumns();

        // If none specified, do all
        if (! $onlyPermissions && ! $onlyRoles && ! $onlyFeatures) {
            $onlyPermissions = true;
            $onlyRoles = true;
            $onlyFeatures = config('mandate.features.enabled', false);
        }

        if ($onlyPermissions) {
            $this->syncPermissions($mandate, $guard, $hasSyncColumns);
        }

        if ($onlyRoles) {
            $this->syncRoles($mandate, $guard, $seed, $hasSyncColumns);
        }

        if ($onlyFeatures) {
            $this->syncFeatures($mandate, $hasSyncColumns);
        }

        $this->newLine();
        $this->info('Sync complete!');

        if (! $seed) {
            $this->components->info('Role-permission relationships were preserved. Use --seed to sync permissions from config.');
        }

        return self::SUCCESS;
    }

    private function syncPermissions(MandateManager $mandate, ?string $guard, bool $hasSyncColumns): void
    {
        $result = null;

        $this->components->task('Syncing permissions', function () use ($mandate, $guard, &$result) {
            $result = $mandate->syncPermissions($guard);

            return true;
        });

        if ($result === null) {
            return;
        }

        $details = sprintf('<fg=green>%d created</>, <fg=yellow>%d existing</>', $result['created'], $result['existing']);

        if ($hasSyncColumns && $result['updated'] > 0) {
            $details .= sprintf(', <fg=cyan>%d updated</>', $result['updated']);
        }

        $this->components->twoColumnDetail('Permissions', $details);
    }

    private function syncRoles(MandateManager $mandate, ?string $guard, bool $seed, bool $hasSyncColumns): void
    {
        $taskLabel = $seed ? 'Syncing roles with permissions' : 'Syncing roles';
        $result = null;

        $this->components->task($taskLabel, function () use ($mandate, $guard, $seed, &$result) {
            $result = $mandate->syncRoles($guard, $seed);

            return true;
        });

        if ($result === null) {
            return;
        }

        $details = sprintf('<fg=green>%d created</>, <fg=yellow>%d existing</>', $result['created'], $result['existing']);

        if ($hasSyncColumns && $result['updated'] > 0) {
            $details .= sprintf(', <fg=cyan>%d updated</>', $result['updated']);
        }

        if ($seed && $result['permissions_synced'] > 0) {
            $details .= sprintf(', <fg=blue>%d permissions synced</>', $result['permissions_synced']);
        }

        $this->components->twoColumnDetail('Roles', $details);
    }

    private function syncFeatures(MandateManager $mandate, bool $hasSyncColumns): void
    {
        $result = null;

        $this->components->task('Syncing features', function () use ($mandate, &$result) {
            $result = $mandate->syncFeatures();

            return true;
        });

        if ($result === null) {
            return;
        }

        $details = sprintf('<fg=green>%d created</>, <fg=yellow>%d existing</>', $result['created'], $result['existing']);

        if ($hasSyncColumns && $result['updated'] > 0) {
            $details .= sprintf(', <fg=cyan>%d updated</>', $result['updated']);
        }

        $this->components->twoColumnDetail('Features', $details);
    }

    /**
     * Check if sync_columns is configured.
     */
    private function hasSyncColumns(): bool
    {
        $config = config('mandate.sync_columns', []);

        return is_array($config) && ! empty($config);
    }
}

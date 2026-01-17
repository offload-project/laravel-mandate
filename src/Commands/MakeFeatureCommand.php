<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Contracts\FeatureGenerator;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generate a new feature class.
 *
 * Delegates to a configured FeatureGenerator implementation.
 */
#[AsCommand(name: 'mandate:feature')]
final class MakeFeatureCommand extends Command
{
    protected $signature = 'mandate:feature
                            {name : The name of the feature}
                            {--guard=web : The guard to use}
                            {--force : Overwrite the feature if it exists}';

    protected $description = 'Create a new feature class (delegates to configured feature generator)';

    public function handle(): int
    {
        $generatorClass = config('mandate.feature_generator');

        if ($generatorClass === null) {
            $this->components->error(
                'No feature generator configured. Set mandate.feature_generator in your config.'
            );
            $this->newLine();
            $this->components->info(
                'Feature generation requires an external package (e.g., Flagged) that provides a FeatureGenerator implementation.'
            );

            return self::FAILURE;
        }

        if (! class_exists($generatorClass)) {
            $this->components->error("Feature generator class '{$generatorClass}' not found.");

            return self::FAILURE;
        }

        if (! is_subclass_of($generatorClass, FeatureGenerator::class)) {
            $this->components->error(
                "Feature generator '{$generatorClass}' must implement ".FeatureGenerator::class
            );

            return self::FAILURE;
        }

        /** @var FeatureGenerator $generator */
        $generator = app($generatorClass);

        /** @var string $name */
        $name = $this->argument('name');

        $path = $generator->generate($name, [
            'guard' => $this->option('guard'),
            'force' => $this->option('force'),
        ]);

        $this->components->info("Feature created successfully at: {$path}");

        return self::SUCCESS;
    }
}

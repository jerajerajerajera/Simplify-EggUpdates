<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Console\Commands;

use Illuminate\Console\Command;
use Simplify\EggUpdates\Services\Runner;

/** Scheduled every minute; checks the update URLs every six hours and acts on new versions as the settings say. */
final class CheckEggUpdates extends Command
{
    protected $signature = 'simplify-egg-updates:check {--force : Check now, also when egg updates are off}';

    protected $description = 'Check every egg for a new version and act on it as the Egg updates settings say.';

    public function handle(): int
    {
        foreach (Runner::run((bool) $this->option('force')) as $line) {
            $this->info($line);
        }

        return self::SUCCESS;
    }
}

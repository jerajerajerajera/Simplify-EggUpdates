<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Providers;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Simplify\EggUpdates\Addon\EggUpdatesAddon;
use Simplify\EggUpdates\Addon\EggUpdatesProblems;

final class EggUpdatesProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shows Egg updates in Simplify's add-on list when Simplify is installed. See Simplify's ADDONS.md.
        $this->app->tag([EggUpdatesAddon::class], 'simplify.addons');
        // Lets Explain explain a failed test from the test server's log. See Simplify-Explain's README, "Explain your plugin's errors".
        $this->app->tag([EggUpdatesProblems::class], 'simplify.problems');
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            Schedule::command('simplify-eggupdates:check')->everyMinute()->withoutOverlapping(30);
        });
    }
}

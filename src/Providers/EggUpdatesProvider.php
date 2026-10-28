<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Providers;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Simplify\EggUpdates\Addon\EggUpdatesAddon;

final class EggUpdatesProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shows Egg updates in Simplify's add-on list when Simplify is installed. See Simplify's ADDONS.md.
        $this->app->tag([EggUpdatesAddon::class], 'simplify.addons');
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            Schedule::command('simplify-egg-updates:check')->everyMinute()->withoutOverlapping(30);
        });
    }
}

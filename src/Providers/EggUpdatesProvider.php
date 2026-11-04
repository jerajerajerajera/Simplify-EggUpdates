<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Providers;

use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\EggUpdates\Addon\EggUpdatesAddon;
use Simplify\EggUpdates\Addon\EggUpdatesProblems;
use Simplify\EggUpdates\Filament\Admin\Pages\EggsList;

final class EggUpdatesProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pelican's Eggs page with Egg Updates in its table (EggsList). Without it the Updates block above the list stays.
        $this->app->bind(ListEggs::class, EggsList::class);
        // Shows Egg updates in Simplify's add-on list when Simplify is installed. See Simplify's ADDONS.md.
        $this->app->tag([EggUpdatesAddon::class], 'simplify.addons');
        // Lets Explain explain a failed test from the test server's log. See Simplify-Explain's README, "Explain your plugin's errors".
        $this->app->tag([EggUpdatesProblems::class], 'simplify.problems');
    }

    public function boot(): void
    {
        // Livewire finds the page again on every click by this name.
        Livewire::component('simplify-eggupdates.eggs-list', EggsList::class);
        $this->app->booted(function () {
            Schedule::command('simplify-eggupdates:check')->everyMinute()->withoutOverlapping(30);
        });
    }
}

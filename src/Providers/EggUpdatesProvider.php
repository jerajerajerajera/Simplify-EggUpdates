<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Providers;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\ComponentHook;
use Livewire\Livewire;
use Simplify\EggUpdates\Addon\EggUpdatesAddon;
use Simplify\EggUpdates\Addon\EggUpdatesProblems;
use Simplify\EggUpdates\Filament\Admin\EggsTable;
use Simplify\EggUpdates\Livewire\EggsTableHook;

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
        // Egg Updates in Pelican's own Eggs table. Without component hooks the Updates block above the list stays.
        if (class_exists(ComponentHook::class) && method_exists(Livewire::getFacadeRoot(), 'componentHook')) {
            Livewire::componentHook(EggsTableHook::class);
        } else {
            EggsTable::trace('Livewire has no component hooks');
        }
        $this->app->booted(function () {
            Schedule::command('simplify-eggupdates:check')->everyMinute()->withoutOverlapping(30);
        });
    }
}

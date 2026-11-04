<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Livewire;

use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use Livewire\ComponentHook;
use Simplify\EggUpdates\Filament\Admin\EggsTable;

/**
 * Adds Egg Updates to Pelican's Eggs table on every request of that page, right after Pelican made the table (Livewire
 * runs this hook after the component's own mount and hydrate). Anything that goes wrong is reported and leaves
 * Pelican's table as it was; the Updates block above the list then shows instead.
 */
final class EggsTableHook extends ComponentHook
{
    public function mount(mixed ...$arguments): void
    {
        $this->extend('mount');
    }

    public function hydrate(mixed ...$arguments): void
    {
        $this->extend('hydrate');
    }

    private function extend(string $when): void
    {
        if (! $this->component instanceof ListEggs) {
            return;
        }
        EggsTable::trace('hook on '.$when);
        try {
            EggsTable::extend($this->component->getTable());
        } catch (\Throwable $exception) {
            EggsTable::trace($exception::class.': '.$exception->getMessage());
            report($exception);
        }
    }
}

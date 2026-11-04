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
        $this->extend();
    }

    public function hydrate(mixed ...$arguments): void
    {
        $this->extend();
    }

    private function extend(): void
    {
        if (! $this->component instanceof ListEggs) {
            return;
        }
        try {
            EggsTable::extend($this->component->getTable());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Livewire;

use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use Livewire\Livewire;
use Simplify\EggUpdates\Filament\Admin\EggsTable;

/**
 * Adds Egg Updates to Pelican's Eggs table on every request of that page, right after Pelican made the table. Livewire
 * runs what a "mount" or "hydrate" listener returns once the component's own mount or hydrate (which builds the table)
 * is done. Anything that goes wrong is reported and leaves Pelican's table as it was; the Updates block above the list
 * then shows instead.
 */
final class EggsTableListener
{
    public static function register(): void
    {
        $listen = match (true) {
            function_exists('Livewire\on') => 'Livewire\on',
            method_exists(Livewire::getFacadeRoot(), 'listen') => [Livewire::getFacadeRoot(), 'listen'],
            default => null,
        };
        if ($listen === null) {
            EggsTable::registered('Livewire has no lifecycle events');

            return;
        }
        foreach (['mount', 'hydrate'] as $event) {
            $listen($event, fn (mixed $component) => $component instanceof ListEggs
                ? fn () => self::extend($component, $event)
                : null);
        }
        EggsTable::registered('listening');
    }

    private static function extend(ListEggs $component, string $event): void
    {
        EggsTable::trace('after '.$event);
        try {
            EggsTable::extend($component->getTable());
        } catch (\Throwable $exception) {
            EggsTable::trace($exception::class.': '.$exception->getMessage());
            report($exception);
        }
    }
}

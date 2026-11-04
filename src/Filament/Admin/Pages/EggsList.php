<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use Filament\Tables\Table;
use Simplify\EggUpdates\Filament\Admin\EggsTable;

/**
 * Pelican's Eggs page with Egg Updates in its table. Pelican has no way for a plugin to add columns or row buttons to
 * this table, so Egg Updates puts this page in the place of Pelican's (EggUpdatesProvider binds it). Everything else is
 * Pelican's own page. When adding Egg Updates fails, for example because a new Pelican changes the table, the error is
 * reported, the table stays as Pelican made it and the Updates block above the list shows instead.
 */
final class EggsList extends ListEggs
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);
        try {
            EggsTable::extend($table);
        } catch (\Throwable $exception) {
            EggsTable::trace($exception::class.': '.$exception->getMessage());
            report($exception);
        }

        return $table;
    }
}

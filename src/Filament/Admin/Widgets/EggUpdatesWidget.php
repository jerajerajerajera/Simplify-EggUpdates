<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Simplify\EggUpdates\Filament\Admin\EggsTable;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;

/**
 * The Updates block above Pelican's Eggs list, for root admins, only while there is something to do. It only shows when
 * Egg Updates could not be added to Pelican's own Eggs table (EggsTable), so there is always one list.
 */
final class EggUpdatesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return UpdatesTable::canManage() && ! EggsTable::extended() && UpdatesTable::hasRows();
    }

    /** Every request of this block, not only the first, comes from a root admin. */
    public function boot(): void
    {
        abort_unless(UpdatesTable::canManage(), 403);
    }

    public function table(Table $table): Table
    {
        return UpdatesTable::configure($table)
            ->heading(__('simplify-eggupdates::egg-updates.title'))
            ->description(UpdatesTable::checked());
    }
}

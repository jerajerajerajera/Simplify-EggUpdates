<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;

/** The Updates block at the top of Pelican's Eggs page, for root admins, only while there is something to do. */
final class EggUpdatesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return UpdatesTable::canManage() && UpdatesTable::hasRows();
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

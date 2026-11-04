<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin;

use App\Filament\Components\Actions\UpdateEggAction;
use Filament\Tables\Table;
use Simplify\EggUpdates\Services\EggTester;

/**
 * Egg Updates inside Pelican's own Eggs table: one list with one search field. A column with the status of each egg,
 * and per row Test first, Update, Move servers and Put back. Pelican's own Update button makes way for Egg Updates'
 * Update, which keeps the previous version, so a row never has two Update buttons.
 *
 * Pelican has no way for a plugin to add columns or row buttons to this table, so EggsTableHook adds them after
 * Pelican made the table. When that fails, for example because a new Pelican changes the table, the Updates block
 * above the list comes back (EggUpdatesWidget), so nothing is lost.
 */
final class EggsTable
{
    private const COLUMN = 'egg_updates_status';

    /** Whether this request's Eggs table has Egg Updates in it; the Updates block only shows when it does not. */
    private static bool $extended = false;

    /** @var list<string> what happened to the Eggs table in this request, for the panel smoke's message */
    private static array $trace = [];

    public static function extended(): bool
    {
        return self::$extended;
    }

    public static function trace(string $line): void
    {
        self::$trace[] = $line;
    }

    /** What happened to the Eggs table in this request, in one line. */
    public static function traced(): string
    {
        return implode('; ', self::$trace) ?: 'the hook never ran';
    }

    public static function extend(Table $table): void
    {
        if (! UpdatesTable::canManage()) {
            self::trace('not a root admin');

            return;
        }
        $columns = $table->getColumns();
        if (! array_key_exists(self::COLUMN, $columns)) {
            $column = UpdatesTable::statusColumn(self::COLUMN)->label(__('simplify-eggupdates::egg-updates.title'));
            $table->columns([...array_values($columns), $column]);
            $actions = array_values(array_filter(
                $table->getRecordActions(),
                fn ($action) => ! self::isPelicansUpdate($action),
            ));
            $table->recordActions([...UpdatesTable::recordActions(inPelicanTable: true), ...$actions])
                ->description(UpdatesTable::checked())
                ->poll(fn () => EggTester::busy() ? '10s' : null);
        }
        self::$extended = true;
    }

    /** For the panel smoke: as at the start of a request. */
    public static function reset(): void
    {
        self::$extended = false;
        self::$trace = [];
    }

    /** Pelican's own Update button, which updates without keeping the previous version. */
    public static function isPelicansUpdate(mixed $action): bool
    {
        return class_exists(UpdateEggAction::class) && $action instanceof UpdateEggAction;
    }
}

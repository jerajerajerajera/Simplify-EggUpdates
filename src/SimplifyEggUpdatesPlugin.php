<?php

declare(strict_types=1);

namespace Simplify\EggUpdates;

use App\Enums\HeaderActionPosition;
use App\Enums\HeaderWidgetPosition;
use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;
use Simplify\EggUpdates\Filament\Admin\Widgets\EggUpdatesWidget;

final class SimplifyEggUpdatesPlugin implements Plugin
{
    public const ID = 'simplify-eggupdates';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'admin') {
            return;
        }
        // The settings page, without a menu item.
        $panel->pages([EggUpdates::class]);
        // The updates live on Pelican's own Eggs page: in its table (EggsTableListener, EggsTable), and Check now and
        // Update all in its title bar. The Updates block above the list only shows when the table could not get them.
        ListEggs::registerCustomHeaderWidgets(HeaderWidgetPosition::Before, EggUpdatesWidget::class);
        ListEggs::registerCustomHeaderActions(HeaderActionPosition::Before, ...UpdatesTable::headerActions());
    }

    public function boot(Panel $panel): void {}
}

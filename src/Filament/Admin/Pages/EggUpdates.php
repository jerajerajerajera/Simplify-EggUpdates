<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Eggs\EggResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;

/**
 * The settings of Egg Updates without Simplify: the gear on Pelican's Eggs page opens it. With Simplify the same form
 * sits in Simplify settings › Add-ons. The updates themselves are a block on the Eggs page, so this page has no menu
 * item. It keeps the address egg-updates, so a link in an older notification still lands in Egg Updates.
 */
final class EggUpdates extends Page
{
    protected static ?string $slug = 'egg-updates';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify-eggupdates::egg-updates';

    public static function canAccess(): bool
    {
        return UpdatesTable::canManage();
    }

    public function getTitle(): string
    {
        return __('simplify-eggupdates::egg-updates.title');
    }

    public function getSubheading(): string
    {
        return UpdatesTable::checked();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eggs')
                ->label(__('simplify-eggupdates::egg-updates.page.eggs'))
                ->icon('tabler-egg')
                ->color('gray')
                ->url(fn () => EggResource::getUrl('index', panel: 'admin')),
        ];
    }
}

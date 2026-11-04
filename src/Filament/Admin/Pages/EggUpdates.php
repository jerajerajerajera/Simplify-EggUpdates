<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Eggs\EggResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;
use Simplify\EggUpdates\Services\Settings;

/**
 * The settings of Egg Updates. The updates themselves are a block on Pelican's Eggs page, so this page has no menu
 * item: Simplify opens it under Simplify settings › Add-ons, and without Simplify the gear on the Eggs page does.
 * It keeps the address egg-updates, so a link in an older notification still lands in Egg Updates.
 */
final class EggUpdates extends Page
{
    protected static ?string $slug = 'egg-updates';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify-eggupdates::egg-updates';

    /** @var array<string, mixed> */
    public ?array $data = [];

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

    public function mount(): void
    {
        $this->form->fill(Settings::formData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(Settings::form())->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eggs')
                ->label(__('simplify-eggupdates::egg-updates.page.eggs'))
                ->icon('tabler-egg')
                ->color('gray')
                ->url(fn () => EggResource::getUrl('index', panel: 'admin')),
            Action::make('save')
                ->label(__('simplify-eggupdates::egg-updates.settings.save'))
                ->icon('tabler-device-floppy')
                ->action(fn () => $this->save())
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        Settings::saveForm($this->form->getState());
        Notification::make()->title(__('simplify-eggupdates::egg-updates.settings.saved'))->success()->send();
    }
}

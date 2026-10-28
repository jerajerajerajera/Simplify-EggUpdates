<?php

declare(strict_types=1);

namespace Simplify\EggUpdates;

use App\Contracts\Plugins\HasPluginSettings;
use Filament\Contracts\Plugin;
use Filament\Notifications\Notification;
use Filament\Panel;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Services\Settings;

final class SimplifyEggUpdatesPlugin implements HasPluginSettings, Plugin
{
    public const ID = 'simplify-egg-updates';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([EggUpdates::class]);
        }
    }

    public function boot(Panel $panel): void {}

    /** The gear on Pelican's Plugins page shows the same form as Settings on the Egg updates page. */
    public function getSettingsFormData(): array
    {
        return Settings::formData();
    }

    public function getSettingsForm(): array
    {
        return Settings::form();
    }

    public function saveSettings(array $data): void
    {
        Settings::saveForm($data);

        Notification::make()->title(__('simplify-egg-updates::egg-updates.settings.saved'))->success()->send();
    }
}

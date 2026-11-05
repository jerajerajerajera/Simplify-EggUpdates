<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Addon;

use Simplify\Core\Addons\Addon;
use Simplify\Core\Filament\Admin\Pages\Settings as SimplifySettings;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Livewire\EggUpdatesSettings;
use Simplify\EggUpdates\Services\EggTester;
use Simplify\EggUpdates\Services\Settings;
use Simplify\EggUpdates\SimplifyEggUpdatesPlugin;

/** How Egg updates shows up inside Simplify. Only loaded when Simplify asks for it. */
final class EggUpdatesAddon extends Addon
{
    public function id(): string
    {
        return SimplifyEggUpdatesPlugin::ID;
    }

    public function name(): string
    {
        return __('simplify-eggupdates::egg-updates.title');
    }

    public function description(): string
    {
        return __('simplify-eggupdates::egg-updates.description');
    }

    public function icon(): string
    {
        return 'tabler-egg';
    }

    /** Egg Updates' section in Simplify settings › Add-ons; on a core before API 8 its own settings page. */
    public function settingsUrl(): ?string
    {
        if (! EggUpdates::canAccess()) {
            return null;
        }

        return method_exists(SimplifySettings::class, 'addonUrl')
            ? SimplifySettings::addonUrl(SimplifyEggUpdatesPlugin::ID)
            : EggUpdates::getUrl(panel: 'admin');
    }

    /**
     * The settings, shown in Egg Updates' section of Simplify settings › Add-ons (API 8).
     *
     * @return class-string<EggUpdatesSettings>
     */
    public function settingsForm(): string
    {
        return EggUpdatesSettings::class;
    }

    /** The temporary servers of Test first stay out of Simplify's lists. */
    public function hiddenServerPrefixes(): array
    {
        return [EggTester::MARK];
    }

    /** Simplify's setup asks when eggs are updated, for root admins. */
    public function setupFields(): array
    {
        return EggUpdates::canAccess() ? Settings::form() : [];
    }

    public function setupState(): array
    {
        return Settings::formData();
    }

    public function saveSetup(array $state): void
    {
        if (EggUpdates::canAccess()) {
            Settings::saveForm($state);
        }
    }
}

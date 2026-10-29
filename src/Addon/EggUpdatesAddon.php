<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Addon;

use Simplify\Core\Addons\Addon;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
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
        return __('simplify-egg-updates::egg-updates.title');
    }

    public function description(): string
    {
        return __('simplify-egg-updates::egg-updates.description');
    }

    public function icon(): string
    {
        return 'tabler-egg';
    }

    public function settingsUrl(): ?string
    {
        return EggUpdates::canAccess() ? EggUpdates::getUrl(panel: 'admin') : null;
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

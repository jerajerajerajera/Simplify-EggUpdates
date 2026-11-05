<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Addon;

use App\Models\Server;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Filament\Admin\Pages\Settings as SimplifySettings;
use Simplify\EggUpdates\Filament\Admin\Pages\EggUpdates;
use Simplify\EggUpdates\Livewire\EggUpdatesSettings;
use Simplify\EggUpdates\Livewire\TestServerActions;
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

    /**
     * A Test first server's page is locked (API 10): you can watch the test, but only Force kill and Retry work, and
     * the server can't be changed or joined (its EULA was accepted for the test only).
     *
     * @return array{notice: string, actions: class-string<TestServerActions>}|null
     */
    public function lockedServer(Server $server): ?array
    {
        if (! EggTester::isTestServer($server)) {
            return null;
        }
        $egg = EggTester::testedEgg($server);

        return [
            'notice' => __('simplify-eggupdates::egg-updates.tests.locked_notice', ['egg' => $egg->name ?? '']),
            'actions' => TestServerActions::class,
        ];
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

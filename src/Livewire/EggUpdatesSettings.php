<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Livewire;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Livewire\Component;
use Simplify\EggUpdates\Filament\Admin\UpdatesTable;
use Simplify\EggUpdates\Services\Settings;

/**
 * The settings of Egg Updates as one form: in Simplify settings › Add-ons (Addon::settingsForm, Simplify's ADDONS.md
 * "Settings"), and on Egg Updates' own settings page without Simplify, so both stay the same.
 *
 * @property-read Schema $form
 */
final class EggUpdatesSettings extends Component implements HasForms
{
    use InteractsWithForms;

    /** On Egg Updates' own page it has its own Save button; inside Simplify's settings, Simplify's Save saves it. */
    public bool $standalone = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(UpdatesTable::canManage(), 403);
        $this->form->fill(Settings::formData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(Settings::form())->statePath('data');
    }

    public function save(): void
    {
        abort_unless(UpdatesTable::canManage(), 403);
        Settings::saveForm($this->form->getState());
        Notification::make()->title(__('simplify-eggupdates::egg-updates.settings.saved'))->success()->send();
    }

    public function render(): mixed
    {
        return view('simplify-eggupdates::livewire.settings');
    }
}

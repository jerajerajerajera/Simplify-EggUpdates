{{-- Simplify asks Save, Discard or Cancel before leaving with changes, and its Save calls save() (ADDONS.md,
     "Unsaved changes"). No <form>: inside Simplify's settings this already sits in Simplify's own form. --}}
<div data-simplify-unsaved="data" data-simplify-save="save" style="display: flex; flex-direction: column; gap: 1rem;">
    {{ $this->form }}
    @if ($standalone)
        <div>
            <x-filament::button wire:click="save" icon="tabler-device-floppy">
                {{ __('simplify-eggupdates::egg-updates.settings.save') }}
            </x-filament::button>
        </div>
    @endif
</div>

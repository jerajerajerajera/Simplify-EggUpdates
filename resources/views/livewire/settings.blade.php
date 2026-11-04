{{-- Simplify asks Save, Discard or Cancel before leaving with changes, and its Save calls save() (ADDONS.md,
     "Unsaved changes"). No <form>: inside Simplify's settings this already sits in Simplify's own form. --}}
<div data-simplify-unsaved="data" data-simplify-save="save" style="display: flex; flex-direction: column; gap: 1rem;">
    {{ $this->form }}
    {{-- When the eggs were last checked, as one grey line under the select; with updates, how many and where. --}}
    <p data-egg-updates-checked style="display: flex; align-items: center; gap: 0.375rem; margin: 0; font-size: 0.8125rem; color: var(--gray-500);">
        <x-filament::icon icon="tabler-clock" style="width: 1rem; height: 1rem;" />
        <span>
            {{ $checked }}
            @if ($available > 0)
                · {{ trans_choice('simplify-eggupdates::egg-updates.messages.available', $available, ['count' => $available]) }}
                · <a href="{{ $eggsUrl }}" style="text-decoration: underline;">{{ __('simplify-eggupdates::egg-updates.page.eggs') }}</a>
            @endif
        </span>
    </p>
    @if ($standalone)
        {{-- Save as the last button on the right (Simplify's ADDONS.md, "Page head"). --}}
        <div style="display: flex; justify-content: flex-end;">
            <x-filament::button wire:click="save" icon="tabler-device-floppy">
                {{ __('simplify-eggupdates::egg-updates.settings.save') }}
            </x-filament::button>
        </div>
    @endif
</div>

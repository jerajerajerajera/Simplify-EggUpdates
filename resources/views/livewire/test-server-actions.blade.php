<div data-egg-updates-test-actions style="display:flex; gap:8px; flex:1;">
    <x-filament::button
        color="danger"
        outlined
        icon="tabler-power"
        wire:click="kill"
        wire:confirm="{{ __('simplify-eggupdates::egg-updates.tests.kill_confirm', ['egg' => $egg]) }}"
        style="flex:1;"
    >{{ __('simplify-eggupdates::egg-updates.tests.force_kill') }}</x-filament::button>
    <x-filament::button
        color="gray"
        outlined
        icon="tabler-refresh"
        wire:click="retry"
        wire:confirm="{{ __('simplify-eggupdates::egg-updates.tests.retry_confirm', ['egg' => $egg]) }}"
        style="flex:1;"
    >{{ __('simplify-eggupdates::egg-updates.tests.retry') }}</x-filament::button>
</div>

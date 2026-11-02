<div x-data="{ copied: false }" style="display: flex; flex-direction: column; gap: 0.75rem;">
    <pre x-ref="log" style="margin: 0; max-height: 18rem; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 0.75rem; line-height: 1.45; padding: 0.625rem; border-radius: 0.5rem; border: 1px solid rgb(127 127 127 / 0.25);">{{ $log }}</pre>
    <div style="display: flex; justify-content: flex-end;">
        <x-filament::button icon="tabler-copy" x-on:click="navigator.clipboard.writeText($refs.log.innerText).then(() => copied = true).catch(() => window.getSelection().selectAllChildren($refs.log))">
            <span x-show="! copied">{{ __('simplify-eggupdates::egg-updates.debug.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('simplify-eggupdates::egg-updates.debug.copied') }}</span>
        </x-filament::button>
    </div>
</div>

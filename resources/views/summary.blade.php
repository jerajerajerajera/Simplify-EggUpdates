@php($row = $getState())
<div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem; padding: 0.75rem;">
    <span style="font-weight: 600;">{{ $row['name'] }}</span>
    <x-filament::badge :color="$row['color']" size="sm">
        {{ __('simplify-eggupdates::egg-updates.status.'.$row['status']) }}
    </x-filament::badge>
    <span class="fi-ta-text-item-label" style="font-size: 0.75rem; opacity: 0.7;">{{ $row['meta'] }}</span>
</div>

<x-filament-panels::page>
    {{-- Simplify asks Save, Discard or Cancel before leaving with changes (Simplify's ADDONS.md, "Unsaved changes"). --}}
    <form wire:submit="save" data-simplify-unsaved>
        {{ $this->form }}
    </form>
</x-filament-panels::page>

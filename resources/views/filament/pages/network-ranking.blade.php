{{-- resources/views/filament/pages/network-ranking.blade.php --}}

<x-filament-panels::page>
    <div class="text-sm text-gray-500 mb-4">
        Comparação das semanas
        <strong>{{ $this->getCurrentRangeLabel() }}</strong>
        ×
        <strong>{{ $this->getPreviousRangeLabel() }}</strong>
    </div>

    {{ $this->table }}
</x-filament-panels::page>

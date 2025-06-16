<x-filament::page>
    <x-filament::card>
        <h2 class="text-xl font-bold mb-4">
            Convidados de {{ $this->user->name }}
        </h2>

        {{ $this->table }}
    </x-filament::card>
</x-filament::page>

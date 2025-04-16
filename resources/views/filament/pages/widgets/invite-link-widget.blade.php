<x-filament::widget>
    <x-filament::card>
        <div class="flex items-center justify-between space-x-4">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Link de Convite</p>
                <p id="invite-link" class="text-sm text-white truncate">{{ $this->getInviteUrl() }}</p>
            </div>

            <button
                x-data="{ copied: false }"
                x-on:click="
                    navigator.clipboard.writeText('{{ $this->getInviteUrl() }}');
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                class="text-sm font-semibold text-primary-600 hover:underline focus:outline-none"
            >
                <template x-if="!copied">
                    <x-heroicon-o-clipboard class="w-5 h-5" />
                </template>
                <template x-if="copied">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                </template>
            </button>
        </div>
    </x-filament::card>
</x-filament::widget>

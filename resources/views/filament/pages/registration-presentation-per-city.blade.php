<x-filament::page>
    <div class="flex flex-col items-center justify-center min-h-[60vh] py-8 text-center">
        <!-- LOGO (Custom Image - Avatar style) -->
        <div class="mb-6 flex justify-center">
            <img src="{{ asset('storage/presentation/time-ac-2026.png') }}" alt="Logo" class="rounded-full object-cover shadow-lg border-2 border-gray-200 dark:border-gray-700" style="width: 200px; height: 200px;" />
        </div>

        <!-- H3 Time André Corrêa -->
        <h3 class="text-xl md:text-2xl font-bold tracking-tight text-gray-700 dark:text-gray-300 mb-6">
            {{ env('APP_NAME') }}
        </h3>

        <!-- Estilo Customizado para o Card -->
        <style>
            .custom-filters-container {
                background-color: transparent !important;
                border: none !important;
                box-shadow: none !important;
            }
            .custom-filters-container label,
            .custom-filters-container .fi-fo-field-wrp label span {
                font-weight: 600 !important;
            }
        </style>
 
        <!-- Container de Filtros Centralizado -->
        <div class="custom-filters-container w-full max-w-2xl p-4 mb-2 text-left">
            {{ $this->form }}
        </div>



        <!-- Grande Destaque do Contador com Atualização Real-time -->
        <div wire:poll.5s class="relative inline-flex flex-col items-center justify-center px-12 py-2">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-indigo-500 tracking-tight transition-all duration-500 ease-in-out" style="font-size: 78px; font-weight: 900;">
                {{ number_format($this->registrationsCount, 0, ',', '.') }}
            </span>
            <div class="absolute -top-2 -right-2 flex h-4 w-4">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500"></span>
            </div>
        </div>
    </div>
</x-filament::page>

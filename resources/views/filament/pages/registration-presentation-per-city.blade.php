<x-filament::page>
    <div class="flex flex-col items-center justify-center min-h-[60vh] py-8 text-center">
        <!-- LOGO (Custom Image - Avatar style) -->
        <div class="mb-6 flex justify-center">
            <img src="{{ asset('storage/presentation/time-ac-2026.png') }}" alt="Logo" class="rounded-full object-cover shadow-lg border-2 border-gray-200 dark:border-gray-700" style="width: 128px; height: 128px;" />
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

            /* Custom styling for counter boxes */
            .counter-card {
                background-color: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 0.75rem;
                padding: 1.5rem;
                min-height: 170px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                text-align: left;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }
            .counter-card:hover {
                transform: scale(1.01);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            }
            .counter-card-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: #52525b !important; /* zinc-600 */
                display: inline-flex;
                align-items: center;
                gap: 0.625rem;
            }
            .counter-card-title svg {
                color: #71717a !important; /* zinc-500 */
            }
            .counter-card-value {
                font-size: 3rem;
                font-weight: 700;
                color: #09090b !important; /* zinc-950 */
                line-height: 1;
                margin-top: 0.75rem;
            }
            .counter-card-subtext {
                font-size: 0.875rem;
                font-weight: 500;
                color: #2563eb !important; /* blue-600 */
                margin-top: 0.75rem;
            }

            /* Dark mode styles */
            .dark .counter-card {
                background-color: #18181b !important;
                border: 1px solid #27272a !important;
            }
            .dark .counter-card-title {
                color: #a1a1aa !important;
            }
            .dark .counter-card-title svg {
                color: #a1a1aa !important;
            }
            .dark .counter-card-value {
                color: #ffffff !important;
            }
            .dark .counter-card-subtext {
                color: #3b82f6 !important;
            }

            @media (min-width: 768px) {
                .counter-card-value {
                    font-size: 3.75rem;
                }
            }
        </style>
 
        <!-- Container de Filtros Centralizado -->
        <div class="custom-filters-container w-full max-w-2xl p-4 mb-2 text-left">
            {{ $this->form }}
        </div>



        <!-- Grande Destaque do Contador com Atualização Real-time -->
        <div wire:poll.5s class="w-full max-w-6xl px-4 mt-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                <!-- MEMBROS CARD (1ª Coluna) -->
                <div class="counter-card relative overflow-hidden">
                    <div class="counter-card-title">
                        <!-- Icon User Group -->
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                        <span>Membros</span>
                    </div>
                    <span class="counter-card-value">
                        {{ number_format($this->membersCount, 0, ',', '.') }}
                    </span>
                    <span class="counter-card-subtext">
                        Membros criados
                    </span>
                </div>

                <!-- EMBAIXADORES CARD (2ª Coluna) -->
                <div class="counter-card relative overflow-hidden">
                    <div class="counter-card-title">
                        <!-- Icon User Plus -->
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235A8.91 8.91 0 009 20.25a8.91 8.91 0 005-1.015m-10 0a12.022 12.022 0 0110 0M4 19.235c.094-.648.306-1.282.63-1.871a12.016 12.016 0 013.882-4.5m5.488 6.371c-.094-.648-.306-1.282-.63-1.871a12.016 12.016 0 00-3.882-4.5m0 0A8.974 8.974 0 019 12.75c-1.397 0-2.7206-.318-3.908-.887" />
                        </svg>
                        <span>Embaixadores</span>
                    </div>
                    <span class="counter-card-value">
                        {{ number_format($this->ambassadorsCount, 0, ',', '.') }}
                    </span>
                    <span class="counter-card-subtext">
                        Embaixadores criados
                    </span>
                </div>

                <!-- TOTAL CARD (3ª Coluna) -->
                <div class="counter-card relative overflow-hidden">
                    <!-- Real-time pulse indicator -->
                    <div class="absolute top-4 right-4 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </div>
                    <div class="counter-card-title">
                        <!-- Icon Globe -->
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 3.03v.568c0 .334.148.65.405.864l1.068.89c.442.369.535 1.01.216 1.49l-.51.766a2.25 2.25 0 01-1.161.886l-.143.048a1.107 1.107 0 00-.57 1.664l.113.17c.23.345.244.79.037 1.15l-.04.069a1.11 1.11 0 01-1.002.583H8.89a2.25 2.25 0 00-1.897 1.03l-.224.373a2.25 2.25 0 01-2.204 1.12H4.951a8.96 8.96 0 01-1.92-5.968C3.03 7.747 6.945 3.83 11.776 3.03z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.918 9a8.964 8.964 0 01-2.825 6.426l-.602-.451a2.25 2.25 0 00-1.834-.37l-.847.212a2.25 2.25 0 00-1.63 2.17v.14a2.25 2.25 0 01-.476 1.373l-.626.782a2.25 2.25 0 00-.476 1.373V21c4.831-.799 8.746-4.717 9.544-9.544c0-.142-.01-.283-.029-.424z" />
                        </svg>
                        <span>Total</span>
                    </div>
                    <span class="counter-card-value">
                        {{ number_format($this->totalCount, 0, ',', '.') }}
                    </span>
                    <span class="counter-card-subtext">
                        Total de Membros e Embaixadores
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-filament::page>

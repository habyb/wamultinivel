<div>
    <div class="mb-4">
        <label for="city" class="block text-sm font-medium text-gray-700">Cidade</label>
        <select wire:model="city" id="city" class="filament-forms-select block w-full">
            <option value="">Selecione uma cidade</option>
            @foreach ($cities as $city)
                <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
    </div>


    @if ($neighborhoods->isNotEmpty())
        <x-filament::card>
            <canvas id="topNeighborhoodsChart"></canvas>
            @push('scripts')
                <script>
                    document.addEventListener('livewire:load', () => {
                        const ctx = document.getElementById('topNeighborhoodsChart').getContext('2d');

                        // Destrói o gráfico anterior se já existe (para evitar duplicação ao mudar cidade)
                        if (window.topNeighborhoodsChartInstance) {
                            window.topNeighborhoodsChartInstance.destroy();
                        }

                        window.topNeighborhoodsChartInstance = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: {!! $neighborhoods->pluck('neighborhood')->map(fn ($b) => $b ?? 'Não informado')->toJson() !!},
                                datasets: [{
                                    data: {!! $neighborhoods->pluck('total')->toJson() !!},
                                    backgroundColor: ['#6366F1', '#10B981', '#F59E0B', '#EF4444', '#3B82F6'],
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                            },
                        });
                    });
                </script>
            @endpush
        </x-filament::card>
    @else
        <x-filament::card>
            <p>Selecione uma cidade para ver o gráfico.</p>
        </x-filament::card>
    @endif
</div>

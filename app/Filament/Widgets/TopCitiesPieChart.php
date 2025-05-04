<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class TopCitiesChart extends ChartWidget
{
    protected static ?int $sort = 1;

    public function getHeading(): string
    {
        return __(key: 'Top 5 cities');
    }

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $user = Auth::user();

        $allGuests = $user->allGuests();

        $cities = $allGuests
            ->where('is_add_email', true)
            ->groupBy('city')
            ->map(fn($cities) => $cities->count())
            ->sortDesc()
            ->take(5);

        return [
            'datasets' => [
                [
                    'label' => __('Users'),
                    'data' => $cities->values()->toArray(),
                    'backgroundColor' => [
                        '#6366F1', // Indigo
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                        '#EF4444', // Red
                        '#3B82F6', // Blue
                    ],
                ],
            ],
            'labels' => $cities->keys()->map(fn($city) => $city ?? __('No records found'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

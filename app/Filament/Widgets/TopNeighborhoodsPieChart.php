<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class TopNeighborhoodsPieChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return __(key: 'Top 5 neighborhoods (city of Rio de Janeiro)');
    }

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $user = Auth::user();

        $allGuests = $user->allGuests();

        $neighborhoods = $allGuests
            ->where('city', 'Rio de Janeiro')
            ->where('is_add_email', true)
            ->groupBy('neighborhood')
            ->map(fn($neighborhoods) => $neighborhoods->count())
            ->sortDesc()
            ->take(5);

        return [
            'datasets' => [
                [
                    'label' => __('Users'),
                    'data' => $neighborhoods->values()->toArray(),
                    'backgroundColor' => [
                        '#6366F1', // Indigo
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                        '#EF4444', // Red
                        '#3B82F6', // Blue
                    ],
                ],
            ],
            'labels' => $neighborhoods->keys()->map(fn($neighborhood) => $neighborhood ?? __('No records found'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

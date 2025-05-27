<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class Concern01PieChart extends ChartWidget
{
    protected static ?int $sort = 5;

    public function getHeading(): string
    {
        return __(key: 'Main concern');
    }

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $user = Auth::user();

        $allGuests = $user->allGuests();

        $concerns = $allGuests
            ->where('is_add_date_of_birth', true)
            ->groupBy('concern_01')
            ->map(fn($concerns) => $concerns->count())
            ->sortDesc()
            ->take(10);

        return [
            'datasets' => [
                [
                    'label' => __('Users'),
                    'data' => $concerns->values()->toArray(),
                    'backgroundColor' => [
                        '#6366F1', // Indigo
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                        '#EF4444', // Red
                        '#3B82F6', // Blue
                        '#8B5CF6', // Violet
                        '#EC4899', // Pink
                        '#22D3EE', // Cyan
                        '#F97316', // Orange
                        '#84CC16', // Lime
                    ],
                ],
            ],
            'labels' => $concerns->keys()->map(fn($concern) => $concern ?? __('No records found'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

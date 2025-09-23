<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;

class GenderPieChart extends ChartWidget
{
    protected static ?int $sort = 5;

    public function getHeading(): string
    {
        return __(key: 'Gender');
    }

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $user = Auth::user();

        $allGuests = $user->allGuests();

        $genders = $allGuests
            ->where('is_add_date_of_birth', true)
            ->groupBy('gender')
            ->map(fn($genders) => $genders->count())
            ->sortDesc()
            ->take(5);

        return [
            'datasets' => [
                [
                    'label' => __('Users'),
                    'data' => $genders->values()->toArray(),
                    'backgroundColor' => [
                        '#6366F1', // Indigo
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                        '#EF4444', // Red
                        '#3B82F6', // Blue
                    ],
                ],
            ],
            'labels' => $genders->keys()->map(fn($gender) => $gender ?? __('No records found'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

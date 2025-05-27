<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\Auth;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

/**
 * Widget to display a pie chart of users grouped by age ranges.
 */
class AgeGroupPieChart extends ChartWidget
{
    protected static ?int $sort = 4;

    public function getHeading(): string
    {
        return __(key: 'Age group');
    }

    protected static ?string $maxHeight = '250px';

    /**
     * Get data for the pie chart.
     *
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = Auth::user();

        $allGuests = $user->allGuests();

        // Get current date to calculate age.
        $today = Carbon::today();

        // We'll fetch all users with a valid date_of_birth, and process them.
        $ageGroups = [
            '16-30' => 0,
            '31-40' => 0,
            '41-50' => 0,
            '51-60' => 0,
            '60+'   => 0,
        ];

        // filter and process
        $filteredGuests = $allGuests
            ->filter(function ($guest) {
                return $guest->is_add_date_of_birth === true && !empty($guest->date_of_birth);
            });

        foreach ($filteredGuests as $guest) {
            try {
                // convert 'dd/mm/yyyy' to Carbon
                $dob = Carbon::createFromFormat('d/m/Y', $guest->date_of_birth);
                $age = $dob->age;

                if ($age >= 16 && $age <= 30) {
                    $ageGroups['16-30']++;
                } elseif ($age >= 31 && $age <= 40) {
                    $ageGroups['31-40']++;
                } elseif ($age >= 41 && $age <= 50) {
                    $ageGroups['41-50']++;
                } elseif ($age >= 51 && $age <= 60) {
                    $ageGroups['51-60']++;
                } elseif ($age > 60) {
                    $ageGroups['60+']++;
                }
            } catch (\Exception $e) {
                // ignore invalid dates
                continue;
            }
        }

        return [
            'datasets' => [
                [
                    'data' => array_values($ageGroups),
                    'backgroundColor' => [
                        '#6366F1', // Indigo
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                    ],
                ],
            ],
            'labels' => array_keys($ageGroups),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

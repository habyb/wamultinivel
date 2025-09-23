<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class NetworkInvitationsChart extends ChartWidget
{
    protected static ?int $sort = 10;

    public function getHeading(): string
    {
        return __(key: 'My network');
    }

    public ?string $filter = 'week';
    protected static string $color = 'primary';

    protected function getFilters(): ?array
    {
        return [
            'week' => __('Last 7 days'),
            'month' => __('Last 30 days'),
            'year' => __('This year'),
        ];
    }

    protected function getData(): array
    {
        [$start, $end, $perMethod] = match ($this->filter) {
            'week'  => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'perDay'],
            'month' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), 'perDay'],
            'year'  => [now()->startOfYear(), now()->endOfYear(), 'perMonth'],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'perDay'],
        };

        $user = Auth::user();
        $networkIds = $user->allNetworkIds();

        $trend = Trend::query(
            User::query()->whereIn('id', $networkIds)->where('is_add_date_of_birth', true)
        )
            ->between(start: $start, end: $end)
            ->{$perMethod}()
            ->count();

        $values = $trend->map(fn(TrendValue $v) => [
            'date'  => Carbon::parse($v->date),
            'value' => $v->aggregate,
        ]);

        return [
            'datasets' => [
                [
                    'label' => __('Registrations in my network'),
                    'data'  => $values->pluck('value')->toArray(),
                ],
            ],
            'labels' => $values->map(
                fn($d) =>
                $d['date']->format($perMethod === 'perDay' ? 'd/m' : 'M')
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

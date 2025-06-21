<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\User;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class WeeklyInvitationsChart extends ChartWidget
{
    protected static ?int $sort = 7;

    public function getHeading(): string
    {
        return __(key: 'Direct registrations');
    }

    public ?string $filter = 'week';

    public function getFilters(): ?array
    {
        return [
            'week' => __('Last 7 days'),
            'month' => __('Last 30 days'),
            'year' => __('This year'),
        ];
    }

    protected function getData(): array
    {
        [$start, $end, $perPeriod] = match ($this->filter) {
            'week'  => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'perDay'],
            'month' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), 'perDay'],
            'year'  => [now()->startOfYear(),            now()->endOfYear(), 'perMonth'],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'perDay'],
        };

        $user = Auth::user();

        $trendQuery = Trend::query(
            User::query()
                ->where('invitation_code', $user->code)
        )
            ->between(start: $start, end: $end)
            ->{$perPeriod}()
            ->count();

        $values = $trendQuery->map(fn(TrendValue $v) => [
            'date'  => Carbon::parse($v->date),
            'value' => $v->aggregate,
        ]);

        return [
            'datasets' => [
                [
                    'label' => __('Direct registrations'),
                    'data'  => $values->pluck('value')->toArray(),
                ],
            ],
            'labels'   => $values->map(fn($item) => $item['date']->format($perPeriod === 'perDay' ? 'd/m' : 'M'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected static string $color = 'primary';
}

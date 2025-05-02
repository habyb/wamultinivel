<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalUsersRegistrationCompleted extends BaseWidget
{
    /**
     * Get the cards for the widget.
     *
     * @return array<int, Card>
     */
    protected function getStats(): array
    {
        $user = Auth::user();

        // Superadmin
        if ($user->hasRole('Superadmin')) {
            return [
                Stat::make(__('Total Registration'), User::query()->where('is_add_email', true)->count())
                    ->description(__('Total users who completed the registration'))
                    ->icon('heroicon-o-users')
                    ->color('success'),
            ];
        }

        // Admin
        if ($user->hasRole('Admin')) {
            return [
                Stat::make(__('Total Registration'), User::query()->where('is_add_email', true)->count())
                    ->description(__('Total users who completed the registration'))
                    ->icon('heroicon-o-users')
                    ->color('success'),
            ];
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return [
                Stat::make(__('Total Registration'), User::query()->where('is_add_email', true)->where('invitation_code', $user->code)->count())
                    ->description(__('Total users who completed the registration'))
                    ->icon('heroicon-o-users')
                    ->color('success'),
            ];
        }

        // fallback
        return parent::getEloquentQuery()->whereRaw('0 = 1')->where('is_add_email', true);
    }
}

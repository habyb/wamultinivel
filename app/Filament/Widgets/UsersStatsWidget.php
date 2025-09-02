<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersStatsWidget extends BaseWidget
{
    // protected static ?int $sort = 2;

    public function getColumns(): int
    {
        $user = Auth::user();

        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            $column = 4;
        } else if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            $column = 3;
        } else {
            $column = 1;
        }

        return $column;
    }

    // protected int | string | array $columnSpan = 'full';

    /**
     * Get the cards for the widget.
     *
     * @return array<int, Card>
     */
    protected function getStats(): array
    {
        $user = Auth::user();
        $total_registration = '';

        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            $total_registration = Stat::make(__('Complete registrations'), $this->getTotalUsersRegistrationCompleted())
                ->description(__('Total users who completed the registration'))
                ->url(route('filament.admin.pages.users-registration-completed'))
                ->icon('heroicon-o-user-group')
                ->color('primary');
        }

        return [

            $total_registration,
            Stat::make(__('Direct registrations'), $this->getTotalDirectRegistrations())
                ->description(__('Total users registered directly'))
                ->url(route('filament.admin.pages.direct-registrations'))
                ->icon('heroicon-o-user-plus')
                ->color('primary'),

            Stat::make(__('My Network'), $user->total_network_count)
                ->description(__('Total users who belong to your network'))
                ->url(route('filament.admin.pages.my-network'))
                ->icon('heroicon-o-globe-americas')
                ->color('primary'),

            Stat::make(__('Total users ambassadors'), $this->getTotalUsersEmbaixadorRole())
                ->description(__('Total users with the ambassador role'))
                ->url(route('filament.admin.pages.total-embaixadores'))
                ->icon('heroicon-o-user-group')
                ->color('primary'),
        ];
    }

    private function getTotalUsersRegistrationCompleted(): int
    {
        $user = Auth::user();

        // Superadmin
        if ($user->hasRole('Superadmin')) {
            return User::query()->where('is_add_date_of_birth', true)->count();
        }

        // Admin
        if ($user->hasRole('Admin')) {
            return User::query()->where('is_add_date_of_birth', true)->count();
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return User::query()->where('is_add_date_of_birth', true)->where('invitation_code', $user->code)->count();
        }

        // fallback
        return parent::getEloquentQuery()->whereRaw('0 = 1')->where('is_add_date_of_birth', true);
    }

    private function getTotalDirectRegistrations(): int
    {
        $user = Auth::user();

        return $user->firstLevelGuests()->count();
    }

    private function getTotalUsersMyNetwork(): int
    {
        $user = Auth::user();

        return $user->totalNetworkOfGuests();
    }

    private function getTotalUsersEmbaixadorRole(): int
    {
        $user = Auth::user();
        $totalEmbaixadores = 0;

        // Superadmin or Admin
        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            $totalEmbaixadores = User::role('Embaixador')->count();
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            $totalEmbaixadores = User::role('Embaixador')->where('invitation_code', $user->code)->count();
        }

        return $totalEmbaixadores;
    }
}

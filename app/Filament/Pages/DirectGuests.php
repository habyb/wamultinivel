<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;

class DirectGuests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static string $view = 'filament.pages.direct-guests';
    protected static ?string $title = 'Convidados';
    protected static ?string $slug = 'direct-guests';
    protected static bool $shouldRegisterNavigation = false;

    public ?User $user = null;

    public function mount(): void
    {
        $userId = request()->query('user');
        $this->user = User::findOrFail($userId);
    }

    protected function getTableQuery(): Builder
    {
        return User::query()
            ->where('invitation_code', $this->user->code)
            ->where('is_add_date_of_birth', true)
            ->with('roles')
            ->withCount('firstLevelGuests');
    }

    public function getTableBulkActions(): array
    {
        return [
            BulkAction::make('exportCsv')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->action(function ($records): StreamedResponse {
                    $filename = 'cadastros_selecionados_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            __('Created at'),
                            __('Invitation ID'),
                            __('Nome'),
                            'WhatsApp',
                            __('Role'),
                            __('Number of guests'),
                            __('Network'),
                            __('Invited by'),
                            __('Gender'),
                            __('Date of Birth'),
                            __('Age'),
                            __('City'),
                            __('Neighborhood'),
                            __('Main concern'),
                            __('Secondary concern')
                        ]);

                        foreach ($records as $user) {
                            try {
                                $dob = Carbon::createFromFormat('d/m/Y', $user->date_of_birth);

                                $isValid = $dob && $dob->format('d/m/Y') === $user->date_of_birth;
                            } catch (\Exception $e) {
                                $isValid = false;
                            }

                            if ($isValid) {
                                $age = $dob->age;
                            } else {
                                $age = '';
                            }

                            fputcsv($handle, [
                                $user->created_at?->format('d/m/Y H:i:s'),
                                $user->code,
                                $user->name,
                                format_phone_number(fix_whatsapp_number($user->remoteJid)),
                                $user->getRoleNames()->join(', '),
                                $user->first_level_guests_count ?? 0,
                                $user->total_network_count,
                                optional($user->referrerGuest)->name . ' - ' . $user->invitation_code,
                                $user->gender,
                                $user->date_of_birth,
                                $age,
                                $user->city,
                                $user->neighborhood,
                                $user->concern_01,
                                $user->concern_02,
                            ]);
                        }

                        fclose($handle);
                    }, $filename);
                }),
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Created at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('code')
                ->label('Invitation ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('name')->label('Nome')
                ->sortable()
                ->searchable(),
            TextColumn::make('remoteJid')
                ->label('WhatsApp')
                ->sortable()
                ->searchable()
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                }),
            TextColumn::make('roles.name')
                ->sortable()
                ->searchable()
                ->badge()
                ->separator(', '),
            TextColumn::make('first_level_guests_count')
                ->label('Convidados')
                ->alignment('right')
                ->sortable()
                ->badge()->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),
            TextColumn::make('total_network_count')
                ->label('Network')
                ->badge()
                ->sortable()
                ->alignment('right')
                ->color(fn(int $state) => match (true) {
                    $state === 0 => 'gray',
                    $state <= 10 => 'primary',
                    $state <= 50 => 'success',
                    default => 'warning',
                }),
            TextColumn::make('gender')
                ->label('Gender')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('date_of_birth')
                ->label('Date of Birth')
                ->sortable()
                ->searchable()
                ->alignment('right')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('city')
                ->label('City')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('neighborhood')
                ->label('Neighborhood')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('concern_01')
                ->label('Main concern')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('concern_02')
                ->label('Secondary concern')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function getTableHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label(__('Export all'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $records = User::with(['roles', 'referrerGuest'])
                        ->withCount('firstLevelGuests')
                        ->where('is_add_date_of_birth', true)
                        ->where('invitation_code', $this->user->code)
                        ->orderBy('name')
                        ->get();

                    $filename = 'cadastros_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            __('Created at'),
                            __('Invitation ID'),
                            __('Nome'),
                            'WhatsApp',
                            __('Role'),
                            __('Number of guests'),
                            __('Network'),
                            __('Invited by'),
                            __('Gender'),
                            __('Date of Birth'),
                            __('Age'),
                            __('City'),
                            __('Neighborhood'),
                            __('Main concern'),
                            __('Secondary concern')
                        ]);

                        foreach ($records as $user) {
                            try {
                                $dob = Carbon::createFromFormat('d/m/Y', $user->date_of_birth);

                                $isValid = $dob && $dob->format('d/m/Y') === $user->date_of_birth;
                            } catch (\Exception $e) {
                                $isValid = false;
                            }

                            if ($isValid) {
                                $age = $dob->age;
                            } else {
                                $age = '';
                            }

                            fputcsv($handle, [
                                $user->created_at?->format('d/m/Y H:i:s'),
                                $user->code,
                                $user->name,
                                format_phone_number(fix_whatsapp_number($user->remoteJid)),
                                $user->getRoleNames()->join(', '),
                                $user->first_level_guests_count ?? 0,
                                $user->total_network_count,
                                optional($user->referrerGuest)->name . ' - ' . $user->invitation_code,
                                $user->gender,
                                $user->date_of_birth,
                                $age,
                                $user->city,
                                $user->neighborhood,
                                $user->concern_01,
                                $user->concern_02,
                            ]);
                        }

                        fclose($handle);
                    }, $filename);
                }),
        ];
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn($record) =>
        $record->first_level_guests_count > 0
            ? DirectGuests::getUrl(['user' => $record->id])
            : null;
    }

    /**
     * Restrict access to Superadmin and Admin roles only.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']);
    }
}

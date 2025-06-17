<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Filament\Tables\Actions\BulkAction;
use Carbon\Carbon;

class UsersRegistrationCompleted extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static string $view = 'filament.pages.users-registrations-completed';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'Complete registrations');
    }

    public function getTitle(): string
    {
        return __(key: 'Complete registrations');
    }

    /**
     * Get the table query.
     */
    protected function getTableQuery(): Builder
    {
        return auth()->user()->completedRegistrationsQuery();
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
                ->action(function ($records) {
                    $records = $records->loadCount('firstLevelGuests');

                    $filename = 'contatos_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            __('Created at'),
                            __('Updated at'),
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
                                $user->updated_at?->format('d/m/Y H:i:s'),
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
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('code')
                ->label('Invitation ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('remoteJid')
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                })
                ->label('WhatsApp')
                ->sortable()
                ->searchable(),
            TextColumn::make('roles.name')
                ->sortable()
                ->searchable()
                ->badge()
                ->separator(', '),
            TextColumn::make('first_level_guests_count')
                ->label('Number of guests')
                ->counts('firstLevelGuests')
                ->badge()
                ->sortable()
                ->alignment('right')
                ->color(fn(string $state): string => match (true) {
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
            TextColumn::make('referrerGuest.name')
                ->label('Invited by')
                ->sortable()
                ->searchable()
                ->formatStateUsing(function ($state, $record) {
                    if (!$state) {
                        return '—';
                    }

                    $nomeLimitado = Str::limit($state, 10, '...');
                    return "{$nomeLimitado} ({$record->invitation_code})";
                })
                ->tooltip(
                    fn($state, $record) =>
                    $state ? "{$record->referrerGuest->name} ({$record->invitation_code})" : null
                ),
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
                        ->orderBy('name')
                        ->get();

                    $filename = 'cadastros_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            __('Created at'),
                            __('Updated at'),
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
                                $user->updated_at?->format('d/m/Y H:i:s'),
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

    /**
     * Restrict access to Superadmin and Admin roles only.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']);
    }
}

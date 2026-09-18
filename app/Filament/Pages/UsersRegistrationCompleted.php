<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Filament\Tables\Actions\BulkAction;
use Carbon\Carbon;


class UsersRegistrationCompleted extends Page implements HasTable
{
    use InteractsWithTable {
        table as traitTable;
    }

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
        $query = auth()->user()
            ->completedRegistrationsQuery()
            ->with(['roles', 'referrerGuest']);

        if (empty($this->tableSortColumn)) {
            $query->orderBy('total_network_count', 'desc');
        }

        return $query;
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('created_at')
                ->label(__('Created at'))
                ->form([
                    DateTimePicker::make('created_from')
                        ->label(__('From'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i'),
                    DateTimePicker::make('created_until')
                        ->label(__('Until'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->where('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->where('created_at', '<=', $date),
                        );
                }),

            SelectFilter::make('city')
                ->label(__('City'))
                ->options(
                    fn () => User::query()
                        ->whereNotNull('city')
                        ->where('is_add_date_of_birth', true)
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->toArray()
                )
                ->searchable(),

            SelectFilter::make('roles')
                ->relationship('roles', 'name')
                ->label(__('Role')),
        ];
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
                    $records = $records->load(['roles', 'referrerGuest'])->loadCount('firstLevelGuests')->sortByDesc('total_network_count');

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
                            __('Date of Birth'),
                            __('Age'),
                            __('City'),
                            __('Neighborhood'),
                            __('Main concern')
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
                                $user->date_of_birth,
                                $age,
                                $user->city,
                                $user->neighborhood,
                                $user->concern_01
                            ]);
                        }

                        fclose($handle);
                    }, $filename);
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('')
                ->tooltip('WhatsApp')
                ->icon(fn () => new \Illuminate\Support\HtmlString('<svg style="width: 1.25rem; height: 1.25rem;" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>'))
                ->color('success')
                ->url(function ($record) {
                    if (!$record?->remoteJid) {
                        return null;
                    }

                    static $textSetting = null;
                    if ($textSetting === null) {
                        $setting = \App\Models\Setting::where('name', 'whatsapp_link_text')->first();
                        $textSetting = $setting ? data_get($setting->payload, 'value', '') : '';
                    }

                    $url = 'https://wa.me/' . fix_whatsapp_number($record->remoteJid);
                    
                    if (!empty($textSetting)) {
                        $message = str_replace('{NAME}', $record->name, $textSetting);
                        $url .= '?text=' . urlencode($message);
                    }

                    return $url;
                })
                ->openUrlInNewTab()
                ->visible(fn ($record) => auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Embaixador']) && filled($record?->remoteJid)),
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
                ->alignment('right')
                ->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),
            TextColumn::make('total_network_count')
                ->label('Network')
                ->badge()
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
                    $records = $this->getFilteredTableQuery()
                        ->with(['roles', 'referrerGuest'])
                        ->withCount('firstLevelGuests')
                        ->orderBy('total_network_count', 'desc')
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
                            __('Date of Birth'),
                            __('Age'),
                            __('City'),
                            __('Neighborhood'),
                            __('Main concern')
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
                                fix_whatsapp_number($user->remoteJid),
                                $user->getRoleNames()->join(', '),
                                $user->first_level_guests_count ?? 0,
                                $user->total_network_count,
                                optional($user->referrerGuest)->name . ' - ' . $user->invitation_code,
                                $user->date_of_birth,
                                $age,
                                $user->city,
                                $user->neighborhood,
                                $user->concern_01
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

    public function table(Tables\Table $table): Tables\Table
    {
        return $this->traitTable($table)
            ->paginated([10, 25, 50, 100]);
    }
}

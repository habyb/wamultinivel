<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MyNetwork extends Page implements HasTable
{
    use InteractsWithTable {
        table as traitTable;
    }

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static string $view = 'filament.pages.my-network';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'My Network');
    }

    public function getTitle(): string
    {
        return __(key: 'My Network');
    }

    /**
     * Get the table query.
     *
     * Aqui garantimos os agregados necessários para ordenação:
     * - first_level_guests_count: total de convidados de 1º nível (withCount)
     * - role_name: menor nome de role associado (withMin) para permitir ORDER BY
     */
    protected function getTableQuery(): Builder
    {
        return auth()->user()
            ->networkGuestsQuery()
            ->with(['roles', 'referrerGuest'])
            ->withMin(
                // relação do Spatie HasRoles
                'roles as role_name',
                'name',
            )
            ->reorder()
            ->orderByDesc('total_network_count');
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
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('code')->label('Invitation ID'),

            TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('remoteJid')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Superadmin', 'Admin']))
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                })
                ->label('WhatsApp')
                ->searchable(),

            /**
             * Para ordenação usamos o alias "role_name" vindo do withMin.
             * Se quiser continuar exibindo múltiplos papéis, mantenha esta coluna;
             * apenas note que ela NÃO será ordenável — quem ordena é a role_name.
             */
            TextColumn::make('roles.name')
                ->label('Funções')
                ->badge()
                ->sortable()
                ->separator(', '),

            TextColumn::make('total_network_count')
                ->label('Number of guests')
                ->badge()
                ->alignment('right')
                ->sortable()
                ->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),

            TextColumn::make('referrerGuest.name')
                ->label('Invited by')
                ->sortable()
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
        ];
    }

    protected function getTableActions(): array
    {
        return [
            \Filament\Tables\Actions\Action::make('whatsapp')
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

    public function table(Tables\Table $table): Tables\Table
    {
        return $this->traitTable($table)
            ->paginated([10, 25, 50, 100]);
    }
}

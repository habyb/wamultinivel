<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersRegistrationPerCity extends Page implements HasTable, Forms\Contracts\HasForms
{
    use InteractsWithTable, Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.users-registration-per-city';

    public ?string $selectedCity = null;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'Registrations by city (daily)');
    }

    public function getTitle(): string
    {
        return __(key: 'Registrations by city (daily)');
    }

    /** Coluna booleana que indica cadastro completo */
    protected function completionColumn(): string
    {
        return Schema::hasColumn('users', 'is_add_date_of_birth')
            ? 'is_add_date_of_birth'
            : 'is_question_date_of_birth';
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        Forms\Components\Select::make('selectedCity')
                            ->label('Cities')
                            ->options(
                                fn() => User::query()
                                    ->whereNotNull('city')
                                    ->where($this->completionColumn(), true)
                                    ->distinct()
                                    ->orderBy('city')
                                    ->pluck('city', 'city')
                                    ->toArray()
                            )
                            ->placeholder('Todas')
                            ->searchable()
                            ->native(false)
                            ->preload()
                            // 1) atualiza SEM debounce e sem esperar blur
                            ->live(debounce: 0)
                            // 2) após mudar, reseta a tabela (recarrega query e página)
                            ->afterStateUpdated(function () {
                                $this->resetTable();
                            })
                            ->columnSpan(3),
                    ])
            ]);
    }

    // Alternativa adicional (opcional): Livewire também chama este hook automaticamente
    // quando a propriedade pública muda. Mantém como “rede de segurança”.
    public function updatedSelectedCity($value): void
    {
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $completed = $this->completionColumn();
        $embaixadorRoleId = \DB::table('roles')->where('name', 'Embaixador')->value('id');

        $q = \App\Models\User::query()
            ->from('users as u')
            ->selectRaw('DATE(u.created_at) as day')
            ->selectRaw('u.city as city')
            ->selectRaw('COUNT(*)::int as total')
            ->selectRaw('COUNT(mhr.role_id)::int as ambassadors_count')
            ->selectRaw('(COUNT(*) - COUNT(mhr.role_id))::int as members_count')
            ->selectRaw("md5((DATE(u.created_at))::text || '|' || coalesce(u.city, '')) as _key")
            ->leftJoin('model_has_roles as mhr', function ($join) use ($embaixadorRoleId) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', '=', User::class)
                    ->where('mhr.role_id', '=', $embaixadorRoleId);
            })
            ->where("u.$completed", true)
            ->where('u.city', '!=', '');

        if ($this->selectedCity) {
            $q->where('u.city', $this->selectedCity);
        }

        // limpa QUALQUER orderBy herdado antes de aplicar o nosso
        return $q->reorder()
            ->groupBy('day', 'u.city')
            ->orderByDesc('day')
            ->orderBy('u.city');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('day')
                ->label('Date')
                ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d/m/Y'))
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('day ' . $direction)
                ),

            Tables\Columns\TextColumn::make('city')
                ->label('Cidade')
                ->searchable()
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('city ' . $direction)
                ),

            Tables\Columns\TextColumn::make('members_count')
                ->label('Membros')
                ->numeric()
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('members_count ' . $direction)
                ),

            Tables\Columns\TextColumn::make('ambassadors_count')
                ->label('Embaixadores')
                ->numeric()
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('ambassadors_count ' . $direction)
                ),

            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->numeric()
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('total ' . $direction)
                ),
        ];
    }

    /** Chave da linha (porque não temos id na agregação) */
    public function getTableRecordKey(mixed $record): string
    {
        return (string) data_get($record, '_key', '');
    }

    /** >>> Impede o Filament de ordenar por users.id (default) */
    public function getTableDefaultSortColumn(): ?string
    {
        return 'day';           // campo que existe na seleção agregada
    }

    public function getTableDefaultSortDirection(): ?string
    {
        return 'desc';
    }

    public function getTableHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label(__('Export all'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $completed = $this->completionColumn();
                    $embaixadorRoleId = \DB::table('roles')->where('name', 'Embaixador')->value('id');

                    // monta a mesma consulta da tabela (agregada por dia+cidade),
                    // respeitando a cidade selecionada (se houver)
                    $q = User::query()
                        ->from('users as u')
                        ->selectRaw('DATE(u.created_at) as day')
                        ->selectRaw('u.city as city')
                        ->selectRaw('COUNT(*)::int as total')
                        ->selectRaw('COUNT(mhr.role_id)::int as ambassadors_count')
                        ->selectRaw('(COUNT(*) - COUNT(mhr.role_id))::int as members_count')
                        ->leftJoin('model_has_roles as mhr', function ($join) use ($embaixadorRoleId) {
                            $join->on('mhr.model_id', '=', 'u.id')
                                ->where('mhr.model_type', '=', User::class)
                                ->where('mhr.role_id', '=', $embaixadorRoleId);
                        })
                        ->where("u.$completed", true)
                        ->where('u.city', '!=', '');

                    if ($this->selectedCity) {
                        $q->where('u.city', $this->selectedCity);
                    }

                    $records = $q
                        ->groupBy('day', 'u.city')
                        ->orderByDesc('day')
                        ->orderBy('u.city')
                        ->get(); // Collection de stdClass com day, city, total, ambassadors_count, members_count

                    $suffix = $this->selectedCity ? ('_' . str($this->selectedCity)->slug('_')) : '_all';
                    $filename = 'cadastros_por_cidade' . $suffix . '_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $handle = fopen('php://output', 'w');

                        // Cabeçalho do CSV
                        // 1. Data, 2. Cidade, 3. Membros, 4. Embaixadores, 5. Total
                        fputcsv($handle, ['Data', 'Cidade', 'Membros', 'Embaixadores', 'Total']);

                        foreach ($records as $row) {
                            fputcsv($handle, [
                                \Carbon\Carbon::parse($row->day)->format('d/m/Y'),
                                (string) $row->city,
                                (int) $row->members_count,
                                (int) $row->ambassadors_count,
                                (int) $row->total,
                            ]);
                        }

                        fclose($handle);
                    }, $filename);
                }),
        ];
    }


    /*
     * Restrict access to Superadmin and Admin roles only.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']);
    }
}

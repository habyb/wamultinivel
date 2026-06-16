<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Tables;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersRegistrationPerCityTotal extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.users-registration-per-city-total';

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'Registrations by city (total)');
    }

    public function getTitle(): string
    {
        return __(key: 'Registrations by city (total)');
    }

    /** Coluna booleana que indica cadastro completo */
    protected function completionColumn(): string
    {
        return Schema::hasColumn('users', 'is_add_date_of_birth')
            ? 'is_add_date_of_birth'
            : 'is_question_date_of_birth';
    }

    /** Consulta agregada: total por cidade */
    protected function getTableQuery(): Builder
    {
        $completed = $this->completionColumn();

        $q = User::query()
            ->from('users as u')
            ->selectRaw('u.city AS city')
            ->selectRaw('COUNT(*)::int AS total')
            ->selectRaw('SUM(CASE WHEN EXISTS (
                SELECT 1 FROM model_has_roles mhr 
                JOIN roles r ON r.id = mhr.role_id 
                WHERE mhr.model_id = u.id 
                  AND mhr.model_type = \'' . User::class . '\' 
                  AND r.name = \'Embaixador\'
            ) THEN 1 ELSE 0 END)::int as ambassadors_count')
            ->selectRaw('COUNT(*)::int - SUM(CASE WHEN EXISTS (
                SELECT 1 FROM model_has_roles mhr 
                JOIN roles r ON r.id = mhr.role_id 
                WHERE mhr.model_id = u.id 
                  AND mhr.model_type = \'' . User::class . '\' 
                  AND r.name = \'Embaixador\'
            ) THEN 1 ELSE 0 END)::int as members_count')
            ->selectRaw("md5(coalesce(u.city, '')) AS _key")
            ->where("u.$completed", true)
            ->whereNotNull('u.city')
            ->where('u.city', '!=', '')
            ->groupBy('u.city');

        // === Ordenação final, guiada pelo estado atual da tabela ===
        // (evita fallback "users.id" e garante que o clique funcione)
        $col = $this->tableSortColumn ?? $this->getTableDefaultSortColumn();
        $dir = $this->tableSortDirection ?? $this->getTableDefaultSortDirection();
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        if ($col === 'city') {
            $q->orderBy('u.city', $dir)->orderBy('total'); // desempate opcional
        } elseif ($col === 'members_count') {
            $q->orderBy('members_count', $dir)->orderBy('u.city');
        } elseif ($col === 'ambassadors_count') {
            $q->orderBy('ambassadors_count', $dir)->orderBy('u.city');
        } else { // default: total
            $q->orderBy('total', $dir)->orderBy('u.city');
        }

        return $q;
    }

    protected function getTableColumns(): array
    {
        return [
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
                ->alignment('right')
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('members_count ' . $direction)
                ),

            Tables\Columns\TextColumn::make('ambassadors_count')
                ->label('Embaixadores')
                ->numeric()
                ->alignment('right')
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('ambassadors_count ' . $direction)
                ),

            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->numeric()
                ->alignment('right')
                ->sortable(
                    query: fn(\Illuminate\Database\Eloquent\Builder $query, string $direction) =>
                    $query->reorder()->orderByRaw('total ' . $direction)
                ),
        ];
    }

    public function getTableRecordKey(mixed $record): string
    {
        return (string) data_get($record, '_key', '');
    }

    public function getTableDefaultSortColumn(): ?string
    {
        return 'total';
    }

    public function getTableDefaultSortDirection(): ?string
    {
        return 'desc';
    }

    /** Exportar CSV (total por cidade) */
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

                    $records = User::query()
                        ->from('users as u')
                        ->selectRaw('u.city AS city')
                        ->selectRaw('COUNT(*)::int AS total')
                        ->selectRaw('COUNT(mhr.role_id)::int as ambassadors_count')
                        ->selectRaw('(COUNT(*) - COUNT(mhr.role_id))::int as members_count')
                        ->leftJoin('model_has_roles as mhr', function ($join) use ($embaixadorRoleId) {
                            $join->on('mhr.model_id', '=', 'u.id')
                                ->where('mhr.model_type', '=', User::class)
                                ->where('mhr.role_id', '=', $embaixadorRoleId);
                        })
                        ->where("u.$completed", true)
                        ->whereNotNull('u.city')
                        ->where('u.city', '!=', '')
                        ->groupBy('u.city')
                        ->orderByDesc('total')
                        ->orderBy('u.city')
                        ->get();

                    $filename = 'cadastros_por_cidade_total_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $h = fopen('php://output', 'w');
                        
                        // Cabeçalho do CSV: Cidade, Membros, Embaixadores, Total
                        fputcsv($h, ['Cidade', 'Membros', 'Embaixadores', 'Total']);

                        foreach ($records as $row) {
                            fputcsv($h, [
                                (string) $row->city,
                                (int) $row->members_count,
                                (int) $row->ambassadors_count,
                                (int) $row->total
                            ]);
                        }

                        fclose($h);
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

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
            ->selectRaw('users.city AS city')
            ->selectRaw('COUNT(*)::int AS quantity')
            ->selectRaw("md5(coalesce(users.city, '')) AS _key")
            ->where("users.$completed", true)
            ->whereNotNull('users.city')
            ->where('users.city', '!=', '')
            ->groupBy('users.city');

        // === Ordenação final, guiada pelo estado atual da tabela ===
        // (evita fallback "users.id" e garante que o clique funcione)
        $col = $this->tableSortColumn ?? $this->getTableDefaultSortColumn();
        $dir = $this->tableSortDirection ?? $this->getTableDefaultSortDirection();
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        if ($col === 'city') {
            $q->orderBy('users.city', $dir)->orderBy('quantity'); // desempate opcional
        } else { // default: quantity
            $q->orderBy('quantity', $dir)->orderBy('users.city');
        }

        return $q;
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('city')
                ->label('City')
                ->searchable()
                ->sortable(),   // sem callback

            TextColumn::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->sortable(),   // sem callback
        ];
    }

    public function getTableRecordKey(mixed $record): string
    {
        return (string) data_get($record, '_key', '');
    }

    public function getTableDefaultSortColumn(): ?string
    {
        return 'quantity';
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

                    $records = User::query()
                        ->selectRaw('users.city AS city')
                        ->selectRaw('COUNT(*)::int AS quantity')
                        ->where("users.$completed", true)
                        ->whereNotNull('users.city')
                        ->where('users.city', '!=', '')
                        ->groupBy('users.city')
                        ->orderByDesc('quantity')
                        ->orderBy('users.city')
                        ->get();

                    $filename = 'cadastros_por_cidade_total_' . now()->format('Ymd_His') . '.csv';

                    return response()->streamDownload(function () use ($records) {
                        $h = fopen('php://output', 'w');
                        fputcsv($h, ['Cidade', 'Quantidade']);

                        foreach ($records as $row) {
                            fputcsv($h, [(string) $row->city, (int) $row->quantity]);
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

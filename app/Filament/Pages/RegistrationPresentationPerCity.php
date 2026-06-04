<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class RegistrationPresentationPerCity extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.registration-presentation-per-city';

    public ?string $selectedCity = null;
    public ?string $selectedDateTime = null;

    public function mount(): void
    {
        $this->selectedDateTime = Carbon::today()->toDateTimeString();

        $this->form->fill([
            'selectedCity' => $this->selectedCity,
            'selectedDateTime' => $this->selectedDateTime,
        ]);
    }

    public static function getNavigationGroup(): string
    {
        return __('Apresentação');
    }

    public static function getNavigationLabel(): string
    {
        return __('Cadastros por Cidade');
    }

    public function getTitle(): string
    {
        return __('Cadastros por Cidade');
    }

    /** Coluna booleana que indica cadastro completo */
    protected function completionColumn(): string
    {
        return Schema::hasColumn('users', 'is_add_date_of_birth')
            ? 'is_add_date_of_birth'
            : 'is_question_date_of_birth';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('selectedCity')
                            ->label(__('Cidade'))
                            ->options(
                                fn() => User::query()
                                    ->whereNotNull('city')
                                    ->where($this->completionColumn(), true)
                                    ->distinct()
                                    ->orderBy('city')
                                    ->pluck('city', 'city')
                                    ->toArray()
                            )
                            ->placeholder(__('Todas'))
                            ->searchable()
                            ->native(false)
                            ->preload()
                            ->live(),

                        Forms\Components\DateTimePicker::make('selectedDateTime')
                            ->label(__('Data/Hora Inicial'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->live(),
                    ])
            ]);
    }

    public function getRegistrationsCountProperty(): int
    {
        $completed = $this->completionColumn();
        $query = User::query()->where($completed, true);

        if ($this->selectedCity) {
            $query->where('city', $this->selectedCity);
        }

        if ($this->selectedDateTime) {
            $query->where('created_at', '>=', $this->selectedDateTime);
        }

        return $query->count();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']);
    }
}

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
                            ->minDate(now()->subDays(30))
                            ->maxDate(now())
                            ->live(),
                    ])
            ]);
    }

    protected ?array $cachedCounts = null;

    protected function getCounts(): array
    {
        if ($this->cachedCounts !== null) {
            return $this->cachedCounts;
        }

        $completed = $this->completionColumn();
        $embaixadorRoleId = \DB::table('roles')->where('name', 'Embaixador')->value('id');

        $query = User::query()
            ->from('users as u')
            ->selectRaw('COUNT(*)::int as total')
            ->selectRaw('COUNT(mhr.role_id)::int as ambassadors')
            ->leftJoin('model_has_roles as mhr', function ($join) use ($embaixadorRoleId) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', '=', User::class)
                    ->where('mhr.role_id', '=', $embaixadorRoleId);
            })
            ->where("u.$completed", true);

        if ($this->selectedCity) {
            $query->where('u.city', $this->selectedCity);
        }

        if ($this->selectedDateTime) {
            $query->where('u.created_at', '>=', $this->selectedDateTime);
        }

        $data = $query->first();

        $total = $data?->total ?? 0;
        $ambassadors = $data?->ambassadors ?? 0;
        $members = max(0, $total - $ambassadors);

        return $this->cachedCounts = [
            'total' => $total,
            'ambassadors' => $ambassadors,
            'members' => $members,
        ];
    }

    public function getTotalCountProperty(): int
    {
        return $this->getCounts()['total'];
    }

    public function getAmbassadorsCountProperty(): int
    {
        return $this->getCounts()['ambassadors'];
    }

    public function getMembersCountProperty(): int
    {
        return $this->getCounts()['members'];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']);
    }
}

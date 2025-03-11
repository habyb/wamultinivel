<?php

namespace App\Filament\Resources\TokenResource\Pages;

use Carbon\Carbon;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Filament\Resources\TokenResource;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Alignment;

class ManageTokens extends ManageRecords
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->modalWidth('md')
            ->form([
                Select::make('user_id')
                    ->options(fn () => User::all()->pluck('name', 'id'))
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->minLength(5)
                    ->maxLength(255)
                    ->unique(),
                DatePicker::make('expires_at')
                    ->native(false)
                    ->displayFormat('d/m/Y')
            ])
            ->action(action: function(array $data){
                $user = User::find($data['user_id']);

                $plainTextToken = $user->createToken(
                    $data['name'],
                    ['*'],
                    $data['expires_at'] ? Carbon::createFromFormat('Y-m-d', $data['expires_at']) : null
                )->plainTextToken;

                $this->replaceMountedAction('showToken', [
                    'token' => $plainTextToken
                ]);

                Notification::make()
                    ->success()
                    ->title(__('Token created successfully!'))
                    ->send();
            })
            ->closeModalByClickingAway(false),
        ];
    }

    public function showTokenAction(): Action
    {
        return Action::make('token')
            ->fillForm(fn(array $arguments) => [
                'token' => $arguments['token'],
            ])
            ->form([
                TextInput::make('token')
                    ->helperText(__('The token is displayed only once upon creation. If you lose it, you will need to delete it and generate a new one.'))
            ])
            ->modalHeading(__('Copy the access token'))
            ->modalIcon('heroicon-o-key')
            ->modalAlignment(Alignment::Center)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->closeModalByClickingAway(false);
    }
}

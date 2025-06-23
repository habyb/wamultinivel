<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use App\Models\User;
use Filament\Actions;
use App\Models\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SentMessageResource;
use Filament\Actions\Action;

class EditSentMessage extends EditRecord
{
    protected static string $resource = SentMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->id();

        $cities = Arr::wrap($data['cities'] ?? []);
        $neighborhoods = Arr::wrap($data['neighborhoods'] ?? []);
        $genders = Arr::wrap($data['genders'] ?? []);
        $ageGroups = Arr::wrap($data['age_groups'] ?? []);
        $concerns01 = Arr::wrap($data['concerns_01'] ?? []);
        $concerns02 = Arr::wrap($data['concerns_02'] ?? []);

        $users = User::query()
            ->when(!empty($cities), function ($query) use ($cities) {
                $query->whereIn('city', $cities);
            })
            ->when(!empty($neighborhoods), function ($query) use ($neighborhoods) {
                $query->whereIn('neighborhood', $neighborhoods);
            })
            ->when(!empty($genders), fn($query) => $query->whereIn('gender', $genders))
            ->when(!empty($concerns01), function ($query) use ($concerns01) {
                $query->where(function ($q) use ($concerns01) {
                    foreach ($concerns01 as $concern) {
                        $q->orWhere('concern_01', 'like', '%' . $concern . '%');
                    }
                });
            })
            ->when(!empty($concerns02), function ($query) use ($concerns02) {
                $query->where(function ($q) use ($concerns02) {
                    foreach ($concerns02 as $concern) {
                        $q->orWhere('concern_02', 'like', '%' . $concern . '%');
                    }
                });
            })
            ->select('id', 'name', 'remoteJid')
            ->get()
            ->filter(function ($user) use ($ageGroups) {
                // check age group
                if (!empty($ageGroups)) {
                    $birth = $user->getParsedDateOfBirth();

                    if (!$birth) {
                        return false;
                    }

                    $age = $birth->age;

                    foreach ($ageGroups as $group) {
                        if (preg_match('/^(\d{2})-(\d{2})$/', $group, $m)) {
                            $min = (int) $m[1];
                            $max = (int) $m[2];

                            if ($age >= $min && $age <= $max) {
                                return true;
                            }
                        }
                    }

                    return false; // age is not within any track
                }

                return true; // no age filter, keep user
            })
            ->values();

        $data['contacts_result'] = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'remoteJid' => $user->remoteJid ?? null
            ];
        })->values()->toArray();

        $data['contacts_count'] = count($data['contacts_result']);

        return $data;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled(fn() => $this->record->status === 'sent');
    }
}

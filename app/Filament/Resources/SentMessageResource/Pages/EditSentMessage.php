<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use App\Filament\Resources\SentMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\User;
use App\Models\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

        $users = User::query()
            ->when($data['cities'] ?? null, fn($q) => $q->whereIn('city', Arr::wrap($data['cities'][0] ?? [])))
            ->when($data['neighborhoods'] ?? null, fn($q) => $q->whereIn('neighborhood', Arr::wrap($data['neighborhoods'][0] ?? [])))
            ->when($data['genders'] ?? null, fn($q) => $q->whereIn('gender', Arr::wrap($data['genders'][0] ?? [])))
            ->when($data['age_groups'] ?? null, function ($q) use ($data) {
                $groups = Arr::wrap($data['age_groups'][0] ?? []);
                $q->where(function ($q2) use ($groups) {
                    foreach ($groups as $group) {
                        if (preg_match('/^(\d{2})-(\d{2})$/', $group, $matches)) {
                            // Ex: "16-30"
                            $minAge = (int) $matches[1];
                            $maxAge = (int) $matches[2];

                            $maxBirthdate = Carbon::now()->subYears($minAge)->endOfDay();
                            $minBirthdate = Carbon::now()->subYears($maxAge)->startOfDay();

                            $q2->orWhereBetween('date_of_birth', [$minBirthdate, $maxBirthdate]);
                        }
                    }
                });
            })
            ->when($data['concerns_01'] ?? null, fn($q) => $q->where(function ($q2) use ($data) {
                foreach (Arr::wrap($data['concerns_01'][0] ?? []) as $concern) {
                    $q2->orWhere('concern_01', 'like', '%' . $concern . '%');
                }
            }))
            ->when($data['concerns_02'] ?? null, fn($q) => $q->where(function ($q2) use ($data) {
                foreach (Arr::wrap($data['concerns_02'][0] ?? []) as $concern) {
                    $q2->orWhere('concern_02', 'like', '%' . $concern . '%');
                }
            }))
            ->select('id', 'name', 'remoteJid')
            ->get()
            ->filter(function ($user) {
                // ✅ Validar se a data é no formato d/m/Y e converter
                try {
                    $birth = is_string($user->date_of_birth)
                        ? Carbon::createFromFormat('d/m/Y', $user->date_of_birth)
                        : Carbon::parse($user->date_of_birth);

                    $user->date_of_birth = $birth->format('Y-m-d');
                    return true;
                } catch (\Exception $e) {
                    Log::warning("Data de nascimento inválida para user #{$user->id}: {$user->date_of_birth}");
                    return false;
                }
            })
            ->values();

        logger()->info('Contatos filtrados:', $users->toArray());

        return $data;
    }
}

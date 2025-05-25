<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use App\Filament\Resources\SentMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class CreateSentMessage extends CreateRecord
{
    protected static string $resource = SentMessageResource::class;

    public function getHeading(): string
    {
        return __('Create message');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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
                            $max = Carbon::now()->subYears((int) $matches[1])->startOfDay();
                            $min = Carbon::now()->subYears((int) $matches[2])->endOfDay();

                            $q2->orWhereBetween('date_of_birth', [$min, $max]);
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
            ->get();

        logger()->info('Contatos filtrados:', $users->toArray());

        return $data;
    }
}

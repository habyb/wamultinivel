<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use App\Filament\Resources\SentMessageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use App\Models\User;
use Illuminate\Support\Arr;

class CreateSentMessage extends CreateRecord
{
    protected static string $resource = SentMessageResource::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Send messages');
    }

    /**
     * Custom breadcrumb trail for this page.
     *
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.send-messages.index') => __('Messages'),
            null => __('Send messages'),
        ];
    }

    /**
     * Customize the head title.
     *
     * @return string
     */
    public function getHeading(): string
    {
        return __('Send messages');
    }

    /**
     * Customize create button.
     *
     * @return string
     */
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('Send'))
            ->icon('heroicon-o-paper-airplane');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Questionary Filter
        if ($data['filter'] == 'questionary') {
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

            // Ambassadors Filter
        } elseif ($data['filter'] == 'ambassadors') {

            $all        = (bool) ($data['all_ambassadors'] ?? false);
            $includeNet = (bool) ($data['include_ambassador_network'] ?? false);

            // IDs base
            if ($all) {
                // todos os usuários que são embaixadores (ajuste o critério se necessário)
                $baseIds = User::query()
                    ->whereHas('firstLevelGuestsNetwork')->where('is_add_date_of_birth', true)
                    ->pluck('id');
            } else {
                // apenas os selecionados no select "Embaixadores"
                $baseIds = collect(\Illuminate\Support\Arr::wrap($data['ambassadors'] ?? []))
                    ->filter()
                    ->unique()
                    ->values();
            }

            // Expansão da rede somente se o toggle estiver ligado
            if ($includeNet) {
                $ids = $baseIds->flatMap(function ($id) {
                    $u = User::find($id);
                    // inclui o próprio embaixador + a rede recursiva dele
                    return $u
                        ? collect([$id])->merge($u->getRecursiveNetWork($u))
                        : collect([$id]);
                })->unique()->values();
            } else {
                // sem rede: somente os IDs base
                $ids = $baseIds->unique()->values();
            }

            // Carrega os destinatários finais (apenas quem tem DOB cadastrado, como no resto do código)
            $users = User::query()
                ->select('id', 'name', 'remoteJid')
                ->whereIn('id', $ids)
                ->where('is_add_date_of_birth', true)
                ->get();

            // Contacts Filter
        } elseif ($data['filter'] == 'contacts') {
            // Network
            if ($data['include_network'] == true) {
                $contactIds = collect(Arr::wrap($data['contacts'] ?? []));

                $ids = $contactIds->values();

                foreach ($contactIds as $contactId) {
                    $user = User::find($contactId);

                    if ($user) {
                        $networkIds = $user->getRecursiveNetWork($user);
                        $ids = $ids->merge($networkIds);
                    }
                }

                $ids = $ids->unique()->values();

                $users = User::query()
                    ->select('id', 'name', 'remoteJid')
                    ->whereIn('id', $ids)
                    ->where('is_add_date_of_birth', true)
                    ->get();
            } else {
                $contacts = Arr::wrap($data['contacts'] ?? []);

                $users = User::query()->select('id', 'name', 'remoteJid')->whereIn('id', $contacts)
                    ->where('is_add_date_of_birth', true)->get();
            }
        }

        $excludedRemoteJids = collect(preg_split('/\R+/', (string)($data['excluded_contacts'] ?? '')))
            ->map(fn($line) => trim($line))
            ->map(fn($line) => preg_replace('/\D+/', '', $line))   // somente dígitos
            ->filter()                                              // remove vazios
            ->map(fn($digits) => "{$digits}@s.whatsapp.net")
            ->values();

        if ($excludedRemoteJids->isNotEmpty()) {
            // garante que $users seja uma Collection
            $users = collect($users)->reject(function ($user) use ($excludedRemoteJids) {
                // $user pode ser array ou objeto (dependendo do select). Cuidamos dos dois.
                $remote = is_array($user) ? ($user['remoteJid'] ?? null) : ($user->remoteJid ?? null);
                return $remote && $excludedRemoteJids->contains($remote);
            })->values();
        }

        // Opcional (evita duplicidade por remoteJid, se houver)
        $users = $users->unique(function ($user) {
            return is_array($user) ? ($user['remoteJid'] ?? null) : ($user->remoteJid ?? null);
        })->values();

        // Mapeia para o payload final já sem os excluídos
        $data['contacts_result'] = $users->map(function ($user) {
            // compatível com array|objeto
            $id   = is_array($user) ? ($user['id'] ?? null)        : ($user->id ?? null);
            $name = is_array($user) ? ($user['name'] ?? null)      : ($user->name ?? null);
            $jid  = is_array($user) ? ($user['remoteJid'] ?? null) : ($user->remoteJid ?? null);

            return [
                'id'        => $id,
                'name'      => $name,
                'remoteJid' => $jid,
            ];
        })->values()->toArray();

        $data['contacts_count'] = count($data['contacts_result']);

        return $data;
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'remoteJid',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermissionTo(permission: 'access_admin');
    }

    public function firstLevelGuests()
    {
        return $this->hasMany(User::class, 'invitation_code', 'code')
            ->where('is_add_date_of_birth', true);
    }

    public function allGuests(): Collection
    {
        $allGuests = collect();

        // Pega os convidados diretos
        $directGuests = $this->firstLevelGuests()->get();

        // Adiciona os diretos à collection
        $allGuests = $allGuests->merge($directGuests);

        // Para cada direto, busca recursivamente os indiretos
        foreach ($directGuests as $guest) {
            $allGuests = $allGuests->merge($guest->allGuests());
        }

        return $allGuests;
    }

    public function referrerGuest()
    {
        return $this->belongsTo(User::class, 'invitation_code', 'code');
    }

    public function totalNetworkOfGuests(): int
    {
        // get first level guests
        $firstLevelIds = $this->firstLevelGuests()->pluck('code')->toArray();

        // count the first-level guests
        $countFirstLevel = count($firstLevelIds);

        // count the indirect (level 2) guests – those invited by the first-level guests.
        $countIndirectGuests = $this->whereIn('invitation_code', $firstLevelIds)->count();

        return $countFirstLevel + $countIndirectGuests;
    }

    /**
     * Get the query for users that belong to this user's network (first and second levels).
     *
     */
    public function networkGuestsQuery()
    {
        // Get the first-level guest codes
        $firstLevelCodes = $this->firstLevelGuests()->pluck('code')->toArray();

        // First-level guests: invited directly
        // Second-level guests: invited by first-level guests
        return static::query()
            ->where('is_add_date_of_birth', true)
            ->where('invitation_code', $this->code) // first-level
            ->orWhereIn('invitation_code', $firstLevelCodes) // second-level
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get a query for listing users with the Embaixador role, scoped by current user's permission.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function embaixadoresQuery()
    {
        $user = Auth::user();

        $query = static::role('Embaixador')->where('is_add_date_of_birth', true);

        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return $query;
        }

        return $query->where('invitation_code', $user->code);
    }

    /**
     * Query to get users who completed registration (is_add_date_of_birth = true),
     * scoped by the current user's role.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function completedRegistrationsQuery()
    {
        $user = Auth::user();

        $query = static::query()->where('is_add_date_of_birth', true);

        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return $query;
        }

        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return $query->where('invitation_code', $user->code);
        }

        // fallback seguro: nenhum resultado
        return static::query()->whereRaw('0 = 1');
    }

    /**
     * Retorna data formatada como Carbon para uso interno (filtros, cálculo de idade).
     */
    public function getParsedDateOfBirth(): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $this->date_of_birth);
        } catch (\Exception $e) {
            return null;
        }
    }
}

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
        return $this->hasMany(User::class, 'invitation_code', 'code');
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
}

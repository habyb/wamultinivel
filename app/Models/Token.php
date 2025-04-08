<?php

namespace App\Models;

use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;

class Token extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $table = 'personal_access_tokens';

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermissionTo(permission: 'access_admin');
    }
}

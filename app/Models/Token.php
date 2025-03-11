<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';
}

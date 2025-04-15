<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppSettings extends Settings
{
    public string $whatsapp_number = '';
    public string $support_email = '';

    public static function group(): string
    {
        return 'app';
    }
}

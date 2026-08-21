<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $group
 * @property string $name
 * @property bool $locked
 * @property array $payload
 */

class Setting extends Model
{
    protected $fillable = [
        'group',
        'name',
        'locked',
        'payload',
    ];

    protected $casts = [
        'locked' => 'boolean',
        'payload' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function ($setting) {
            if ($setting->name === 'whatsapp_token') {
                Cache::forget('uazapi_token');
            }
        });

        static::deleted(function ($setting) {
            if ($setting->name === 'whatsapp_token') {
                Cache::forget('uazapi_token');
            }
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

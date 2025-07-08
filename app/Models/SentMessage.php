<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentMessage extends Model
{
    protected $fillable = [
        'title',
        'cities',
        'neighborhoods',
        'genders',
        'age_groups',
        'concerns_01',
        'concerns_02',
        'status',
        'sent_at',
        'type',
        'path',
        'description',
        'contacts_result',
        'contacts_count',
        'template_id',
        'template_name',
        'template_language',
        'template_components',
        'filter',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'cities' => 'array',
        'neighborhoods' => 'array',
        'genders' => 'array',
        'age_groups' => 'array',
        'concerns_01' => 'array',
        'concerns_02' => 'array',
        'contacts_result' => 'array',
        'template_components' => 'array',
    ];
}

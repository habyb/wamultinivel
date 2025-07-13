<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentMessagesLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sent_message_id',
        'contact_name',
        'remote_jid',
        'message_status',
        'sent_at',
    ];
}

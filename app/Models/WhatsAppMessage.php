<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $attributes = ['generation' => 0];

    protected $guarded = [];

    protected $hidden = ['body'];

    protected $casts = ['generation' => 'integer', 'body' => 'encrypted'];

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }
}

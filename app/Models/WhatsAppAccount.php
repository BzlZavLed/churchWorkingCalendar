<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $attributes = ['generation' => 0];

    protected $guarded = [];

    protected $hidden = ['sender', 'sender_hash', 'link_hash', 'state'];

    protected $casts = ['generation' => 'integer', 'sender' => 'encrypted', 'state' => 'encrypted:array', 'link_expires_at' => 'datetime', 'last_received_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function senderHash(string $sender): string
    {
        return hash_hmac('sha256', $sender, config('app.key'));
    }
}

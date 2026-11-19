<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInteraction extends Model
{
    protected $table = 'whatsapp_interactions';
    protected $guarded = [];
    protected $hidden = ['content'];
    public function user() { return $this->belongsTo(User::class); }
    public function message() { return $this->belongsTo(WhatsAppMessage::class); }
    protected $casts = ['content' => 'encrypted'];
}

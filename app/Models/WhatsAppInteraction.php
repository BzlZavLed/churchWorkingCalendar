<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInteraction extends Model
{
    protected $table = 'whatsapp_interactions';
    protected $guarded = [];
    protected $hidden = ['content'];
    protected $casts = ['content' => 'encrypted'];
}

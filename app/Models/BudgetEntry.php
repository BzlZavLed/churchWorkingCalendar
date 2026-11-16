<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetEntry extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['amount_cents' => 'integer'];

    protected $hidden = ['request_hash'];
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }

    public function author() { return $this->belongsTo(User::class, 'created_by'); }
}

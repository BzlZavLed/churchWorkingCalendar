<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Church extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'conference_name',
        'pastor_name',
        'address',
        'ethnicity',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function latestInvitation(): HasOne
    {
        return $this->hasOne(Invitation::class)->latestOfMany();
    }
}

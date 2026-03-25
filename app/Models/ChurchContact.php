<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChurchContact extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ChurchContact $contact) {
            if (empty($contact->consent_token)) {
                $contact->consent_token = Str::random(64);
            }
        });
    }

    protected $fillable = [
        'church_id',
        'department_id',
        'created_by',
        'name',
        'phone',
        'email',
        'address',
        'is_sda',
        'sms_consent',
        'sms_consented_at',
        'email_consent',
        'email_consented_at',
        'consent_token',
    ];

    protected $casts = [
        'is_sda' => 'boolean',
        'sms_consent' => 'boolean',
        'sms_consented_at' => 'datetime',
        'email_consent' => 'boolean',
        'email_consented_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

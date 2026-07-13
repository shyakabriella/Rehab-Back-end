<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'certificate_number',
        'award_type',
        'title',
        'awarded_at',
        'awarded_by',
        'status',
        'revoked_at',
        'revoked_by',
        'notes',
        'criteria_snapshot',
    ];

    protected $casts = [
        'awarded_at' => 'date',
        'revoked_at' => 'datetime',
        'criteria_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}

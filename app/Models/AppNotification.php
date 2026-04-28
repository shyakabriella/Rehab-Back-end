<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'created_by',
        'title',
        'message',
        'type',
        'priority',
        'status',
        'data',
        'scheduled_at',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'sent_at' => 'datetime:Y-m-d H:i:s',
        'read_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}
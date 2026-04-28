<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'reminder_type',
        'frequency',
        'remind_time',
        'remind_date',
        'days_of_week',
        'status',
        'last_triggered_at',
        'next_trigger_at',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'remind_date' => 'date:Y-m-d',
        'last_triggered_at' => 'datetime:Y-m-d H:i:s',
        'next_trigger_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
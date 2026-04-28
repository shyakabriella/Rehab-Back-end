<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mood_log_id',
        'trigger_log_id',
        'title',
        'assistant_type',
        'prompt_file',
        'prompt_version',
        'status',
        'risk_level',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moodLog(): BelongsTo
    {
        return $this->belongsTo(MoodLog::class);
    }

    public function triggerLog(): BelongsTo
    {
        return $this->belongsTo(TriggerLog::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'ai_session_id')
            ->orderBy('created_at');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trigger_type',
        'intensity_level',
        'location',
        'coping_action',
        'result',
        'notes',
        'triggered_at',
    ];

    protected $casts = [
        'intensity_level' => 'integer',
        'triggered_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
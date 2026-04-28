<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mood',
        'stress_level',
        'craving_level',
        'energy_level',
        'sleep_quality',
        'had_craving',
        'main_trigger',
        'notes',
        'logged_date',
    ];

    protected $casts = [
        'had_craving' => 'boolean',
        'logged_date' => 'date:Y-m-d',
        'stress_level' => 'integer',
        'craving_level' => 'integer',
        'energy_level' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
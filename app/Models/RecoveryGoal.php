<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'progress_percent',
        'start_date',
        'target_date',
        'completed_date',
        'notes',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'start_date' => 'date:Y-m-d',
        'target_date' => 'date:Y-m-d',
        'completed_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
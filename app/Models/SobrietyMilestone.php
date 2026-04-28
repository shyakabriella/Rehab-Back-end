<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SobrietyMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'milestone_type',
        'milestone_days',
        'achieved_date',
        'status',
        'title',
        'notes',
    ];

    protected $casts = [
        'milestone_days' => 'integer',
        'achieved_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
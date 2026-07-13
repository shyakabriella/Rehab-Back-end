<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'condition_type',
        'condition_name',
        'diagnosis_status',
        'care_status',
        'severity',
        'diagnosed_at',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'diagnosed_at' => 'date',
        'resolved_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

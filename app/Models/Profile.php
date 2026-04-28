<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gender',
        'age_range',
        'addiction_type',
        'recovery_stage',
        'recovery_start_date',
        'main_goal',
        'support_level',
        'privacy_mode',
        'emergency_contact_name',
        'emergency_contact_phone',
        'bio',
    ];

    protected $casts = [
        'recovery_start_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
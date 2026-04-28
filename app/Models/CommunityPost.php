<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'community_group_id',
        'user_id',
        'title',
        'body',
        'mood_tag',
        'is_anonymous',
        'status',
        'support_count',
        'comments_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'support_count' => 'integer',
        'comments_count' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'community_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReportedContent::class, 'community_post_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_category_id',
        'created_by',
        'title',
        'slug',
        'summary',
        'content',
        'resource_type',
        'media_url',
        'external_url',
        'thumbnail',
        'status',
        'is_featured',
        'views_count',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
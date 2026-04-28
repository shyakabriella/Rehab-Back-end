<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'is_anonymous',
        'anonymous_name',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_anonymous' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    public function triggerLogs(): HasMany
    {
        return $this->hasMany(TriggerLog::class);
    }

    public function sobrietyMilestones(): HasMany
    {
        return $this->hasMany(SobrietyMilestone::class);
    }

    public function recoveryGoals(): HasMany
    {
        return $this->hasMany(RecoveryGoal::class);
    }

    public function aiSessions(): HasMany
    {
        return $this->hasMany(AiSession::class);
    }

    public function aiMessages(): HasMany
    {
        return $this->hasManyThrough(AiMessage::class, AiSession::class);
    }

    public function communityGroupsCreated(): HasMany
    {
        return $this->hasMany(CommunityGroup::class, 'created_by');
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function communityComments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    public function reportedContents(): HasMany
    {
        return $this->hasMany(ReportedContent::class, 'reporter_user_id');
    }

    public function reviewedReports(): HasMany
    {
        return $this->hasMany(ReportedContent::class, 'reviewed_by');
    }

    public function campaignsCreated(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    public function campaignContentsCreated(): HasMany
    {
        return $this->hasMany(CampaignContent::class, 'created_by');
    }

    public function resourcesCreated(): HasMany
    {
        return $this->hasMany(Resource::class, 'created_by');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function notificationsCreated(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function isAdmin(): bool
    {
        return optional($this->role)->name === 'admin';
    }

    public function isModerator(): bool
    {
        return optional($this->role)->name === 'moderator';
    }

    public function isUser(): bool
    {
        return optional($this->role)->name === 'user';
    }

    public function canModerateCommunity(): bool
    {
        return $this->isAdmin() || $this->isModerator();
    }

    public function canManageAwareness(): bool
    {
        return $this->isAdmin() || $this->isModerator();
    }

    public function canManageNotifications(): bool
    {
        return $this->isAdmin() || $this->isModerator();
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous && !empty($this->anonymous_name)) {
            return $this->anonymous_name;
        }

        return $this->name;
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AppNotification;
use App\Models\Campaign;
use App\Models\CommunityComment;
use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Models\DeviceToken;
use App\Models\MoodLog;
use App\Models\RecoveryGoal;
use App\Models\Reminder;
use App\Models\ReportedContent;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Role;
use App\Models\SobrietyMilestone;
use App\Models\TriggerLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends BaseController
{
    /**
     * Register API
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'phone'           => 'nullable|string|max:20|unique:users,phone',
            'password'        => 'required|string|min:6',
            'c_password'      => 'required|same:password',
            'is_anonymous'    => 'nullable|boolean',
            'anonymous_name'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $userRole = Role::where('name', 'user')->first();

        if (!$userRole) {
            return $this->sendError('Role setup error.', [
                'error' => 'Default user role not found. Please run RoleSeeder first.'
            ]);
        }

        $user = User::create([
            'role_id'         => $userRole->id,
            'name'            => $request->name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'password'        => $request->password,
            'status'          => 'active',
            'is_anonymous'    => $request->boolean('is_anonymous'),
            'anonymous_name'  => $request->anonymous_name,
        ]);

        $user->load(['role', 'profile']);

        $success = [
            'token' => $user->createToken('rehab-app')->plainTextToken,
            'user'  => $this->userData($user),
        ];

        return $this->sendResponse($success, 'User registered successfully.');
    }

    /**
     * Login API
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user = User::with(['role', 'profile'])
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid credentials.'
            ]);
        }

        if ($user->status !== 'active') {
            return $this->sendError('Account inactive.', [
                'error' => 'Your account is not active.'
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid credentials.'
            ]);
        }

        $success = [
            'token' => $user->createToken('rehab-app')->plainTextToken,
            'user'  => $this->userData($user),
        ];

        return $this->sendResponse($success, 'User login successfully.');
    }

    /**
     * Get authenticated user full dashboard data
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load(['role', 'profile']);

        return $this->sendResponse(
            $this->userData($user),
            'User data fetched successfully.'
        );
    }

    /**
     * Logout API
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'User logout successfully.');
    }

    /**
     * Format user response data
     */
    private function userData(User $user): array
    {
        /*
        |--------------------------------------------------------------------------
        | Mood Update
        |--------------------------------------------------------------------------
        */

        $latestMoodLog = MoodLog::where('user_id', $user->id)
            ->orderByDesc('logged_date')
            ->first();

        $recentMoodLogs = MoodLog::where('user_id', $user->id)
            ->orderByDesc('logged_date')
            ->limit(7)
            ->get();

        $totalMoodLogs = MoodLog::where('user_id', $user->id)->count();

        $averageStress = MoodLog::where('user_id', $user->id)
            ->whereNotNull('stress_level')
            ->avg('stress_level');

        $averageCraving = MoodLog::where('user_id', $user->id)
            ->whereNotNull('craving_level')
            ->avg('craving_level');

        $highCravingDays = MoodLog::where('user_id', $user->id)
            ->where('craving_level', '>=', 7)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Trigger Update
        |--------------------------------------------------------------------------
        */

        $latestTriggerLog = TriggerLog::where('user_id', $user->id)
            ->orderByDesc('triggered_at')
            ->orderByDesc('created_at')
            ->first();

        $recentTriggerLogs = TriggerLog::where('user_id', $user->id)
            ->orderByDesc('triggered_at')
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();

        $totalTriggers = TriggerLog::where('user_id', $user->id)->count();

        $averageTriggerIntensity = TriggerLog::where('user_id', $user->id)
            ->whereNotNull('intensity_level')
            ->avg('intensity_level');

        $highIntensityTriggers = TriggerLog::where('user_id', $user->id)
            ->where('intensity_level', '>=', 7)
            ->count();

        $relapseCount = TriggerLog::where('user_id', $user->id)
            ->where('result', 'relapsed')
            ->count();

        $mostCommonTrigger = TriggerLog::where('user_id', $user->id)
            ->selectRaw('trigger_type, COUNT(*) as total')
            ->groupBy('trigger_type')
            ->orderByDesc('total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Sobriety Update
        |--------------------------------------------------------------------------
        */

        $recoveryStartDate = optional($user->profile)->recovery_start_date;

        $soberDays = 0;

        if ($recoveryStartDate) {
            $startDate = Carbon::parse($recoveryStartDate)->startOfDay();
            $today = now()->startOfDay();

            if ($startDate->lessThanOrEqualTo($today)) {
                $soberDays = $startDate->diffInDays($today) + 1;
            }
        }

        $totalSobrietyMilestones = SobrietyMilestone::where('user_id', $user->id)->count();

        $achievedSobrietyMilestones = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'achieved')
            ->count();

        $inProgressSobrietyMilestones = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->count();

        $resetSobrietyMilestones = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'reset')
            ->count();

        $latestSobrietyMilestone = SobrietyMilestone::where('user_id', $user->id)
            ->orderByDesc('achieved_date')
            ->orderByDesc('created_at')
            ->first();

        $nextSobrietyMilestone = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where('milestone_days', '>', $soberDays)
            ->orderBy('milestone_days')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Recovery Goals Update
        |--------------------------------------------------------------------------
        */

        $latestRecoveryGoal = RecoveryGoal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $recentRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $totalRecoveryGoals = RecoveryGoal::where('user_id', $user->id)->count();

        $completedRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $inProgressRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->count();

        $notStartedRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->where('status', 'not_started')
            ->count();

        $cancelledRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->count();

        $highPriorityRecoveryGoals = RecoveryGoal::where('user_id', $user->id)
            ->where('priority', 'high')
            ->count();

        $averageGoalProgress = RecoveryGoal::where('user_id', $user->id)
            ->avg('progress_percent');

        $nextTargetGoal = RecoveryGoal::where('user_id', $user->id)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->whereNotNull('target_date')
            ->orderBy('target_date')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | AI Assistant Update
        |--------------------------------------------------------------------------
        */

        $latestAiSession = AiSession::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $totalAiSessions = AiSession::where('user_id', $user->id)->count();

        $activeAiSessions = AiSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $highRiskAiSessions = AiSession::where('user_id', $user->id)
            ->whereIn('risk_level', ['high', 'crisis'])
            ->count();

        $totalAiMessages = AiMessage::whereHas('session', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        /*
        |--------------------------------------------------------------------------
        | Community Update
        |--------------------------------------------------------------------------
        */

        $myCommunityPosts = CommunityPost::where('user_id', $user->id)->count();

        $myCommunityComments = CommunityComment::where('user_id', $user->id)->count();

        $myReports = ReportedContent::where('reporter_user_id', $user->id)->count();

        $latestCommunityPost = CommunityPost::where('status', 'published')
            ->orderByDesc('created_at')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Awareness Update
        |--------------------------------------------------------------------------
        */

        $featuredCampaign = Campaign::where('status', 'published')
            ->where('is_featured', true)
            ->orderByDesc('created_at')
            ->first();

        $latestResource = Resource::where('status', 'published')
            ->orderByDesc('created_at')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Notification / Reminder Update
        |--------------------------------------------------------------------------
        */

        $latestNotification = AppNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $nextReminder = Reminder::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('next_trigger_at')
            ->orderBy('next_trigger_at')
            ->first();

        $unreadNotifications = AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $activeReminders = Reminder::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $activeDeviceTokens = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'display_name'   => $user->display_name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'status'         => $user->status,
            'role'           => optional($user->role)->name,
            'is_anonymous'   => $user->is_anonymous,
            'anonymous_name' => $user->anonymous_name,

            'profile' => $user->profile ? [
                'id'                      => $user->profile->id,
                'gender'                  => $user->profile->gender,
                'age_range'               => $user->profile->age_range,
                'addiction_type'          => $user->profile->addiction_type,
                'recovery_stage'          => $user->profile->recovery_stage,
                'recovery_start_date'     => $user->profile->recovery_start_date
                    ? Carbon::parse($user->profile->recovery_start_date)->format('Y-m-d')
                    : null,
                'main_goal'               => $user->profile->main_goal,
                'support_level'           => $user->profile->support_level,
                'privacy_mode'            => $user->profile->privacy_mode,
                'emergency_contact_name'  => $user->profile->emergency_contact_name,
                'emergency_contact_phone' => $user->profile->emergency_contact_phone,
                'bio'                     => $user->profile->bio,
            ] : null,

            'mood_update' => [
                'latest_mood_log' => $latestMoodLog,
                'summary' => [
                    'total_logs'             => $totalMoodLogs,
                    'average_stress_level'   => $averageStress ? round($averageStress, 2) : 0,
                    'average_craving_level'  => $averageCraving ? round($averageCraving, 2) : 0,
                    'high_craving_days'      => $highCravingDays,
                ],
                'recent_mood_logs' => $recentMoodLogs,
            ],

            'trigger_update' => [
                'latest_trigger_log' => $latestTriggerLog,
                'summary' => [
                    'total_triggers'             => $totalTriggers,
                    'average_intensity_level'    => $averageTriggerIntensity ? round($averageTriggerIntensity, 2) : 0,
                    'high_intensity_triggers'    => $highIntensityTriggers,
                    'relapse_count'              => $relapseCount,
                    'most_common_trigger'        => $mostCommonTrigger ? [
                        'trigger_type' => $mostCommonTrigger->trigger_type,
                        'total'        => $mostCommonTrigger->total,
                    ] : null,
                ],
                'recent_trigger_logs' => $recentTriggerLogs,
            ],

            'sobriety_update' => [
                'recovery_start_date' => $recoveryStartDate
                    ? Carbon::parse($recoveryStartDate)->format('Y-m-d')
                    : null,
                'current_sober_days'       => $soberDays,
                'total_milestones'         => $totalSobrietyMilestones,
                'achieved_milestones'      => $achievedSobrietyMilestones,
                'in_progress_milestones'   => $inProgressSobrietyMilestones,
                'reset_milestones'         => $resetSobrietyMilestones,
                'latest_milestone'         => $latestSobrietyMilestone,
                'next_milestone'           => $nextSobrietyMilestone,
            ],

            'recovery_goals_update' => [
                'latest_goal' => $latestRecoveryGoal,
                'summary' => [
                    'total_goals'              => $totalRecoveryGoals,
                    'completed_goals'          => $completedRecoveryGoals,
                    'in_progress_goals'        => $inProgressRecoveryGoals,
                    'not_started_goals'        => $notStartedRecoveryGoals,
                    'cancelled_goals'          => $cancelledRecoveryGoals,
                    'high_priority_goals'      => $highPriorityRecoveryGoals,
                    'average_progress_percent' => $averageGoalProgress ? round($averageGoalProgress, 2) : 0,
                ],
                'next_target_goal' => $nextTargetGoal,
                'recent_goals'     => $recentRecoveryGoals,
            ],

            'ai_update' => [
                'latest_session' => $latestAiSession,
                'summary' => [
                    'total_sessions'     => $totalAiSessions,
                    'active_sessions'    => $activeAiSessions,
                    'high_risk_sessions' => $highRiskAiSessions,
                    'total_messages'     => $totalAiMessages,
                ],
            ],

            'community_update' => [
                'summary' => [
                    'active_groups' => CommunityGroup::where('is_active', true)->count(),
                    'published_posts' => CommunityPost::where('status', 'published')->count(),
                    'my_posts' => $myCommunityPosts,
                    'my_comments' => $myCommunityComments,
                    'my_reports' => $myReports,
                ],
                'latest_post' => $latestCommunityPost,
            ],

            'awareness_update' => [
                'summary' => [
                    'published_campaigns' => Campaign::where('status', 'published')->count(),
                    'featured_campaigns' => Campaign::where('status', 'published')
                        ->where('is_featured', true)
                        ->count(),
                    'published_resources' => Resource::where('status', 'published')->count(),
                    'active_categories' => ResourceCategory::where('is_active', true)->count(),
                ],
                'featured_campaign' => $featuredCampaign,
                'latest_resource' => $latestResource,
            ],

            'notification_update' => [
                'latest_notification' => $latestNotification,
                'next_reminder' => $nextReminder,
                'summary' => [
                    'unread_notifications' => $unreadNotifications,
                    'active_reminders' => $activeReminders,
                    'active_device_tokens' => $activeDeviceTokens,
                ],
            ],
        ];
    }
}
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\MoodLogController;
use App\Http\Controllers\API\TriggerLogController;
use App\Http\Controllers\API\SobrietyMilestoneController;
use App\Http\Controllers\API\RecoveryGoalController;
use App\Http\Controllers\API\AiAssistantController;
use App\Http\Controllers\API\CommunityController;
use App\Http\Controllers\API\AwarenessController;
use App\Http\Controllers\API\NotificationController;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authenticated User Routes
    |--------------------------------------------------------------------------
    */

    Route::get('me', [RegisterController::class, 'me']);
    Route::post('logout', [RegisterController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Profile Routes
    |--------------------------------------------------------------------------
    */

    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'store']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Mood Log Routes
    |--------------------------------------------------------------------------
    */

    Route::get('mood-logs', [MoodLogController::class, 'index']);
    Route::post('mood-logs', [MoodLogController::class, 'store']);
    Route::get('mood-logs/today', [MoodLogController::class, 'today']);
    Route::get('mood-logs/summary', [MoodLogController::class, 'summary']);
    Route::get('mood-logs/{id}', [MoodLogController::class, 'show']);
    Route::put('mood-logs/{id}', [MoodLogController::class, 'update']);
    Route::delete('mood-logs/{id}', [MoodLogController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Trigger Log Routes
    |--------------------------------------------------------------------------
    */

    Route::get('trigger-logs', [TriggerLogController::class, 'index']);
    Route::post('trigger-logs', [TriggerLogController::class, 'store']);
    Route::get('trigger-logs/summary', [TriggerLogController::class, 'summary']);
    Route::get('trigger-logs/{id}', [TriggerLogController::class, 'show']);
    Route::put('trigger-logs/{id}', [TriggerLogController::class, 'update']);
    Route::delete('trigger-logs/{id}', [TriggerLogController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Sobriety Milestone Routes
    |--------------------------------------------------------------------------
    */

    Route::get('sobriety-milestones', [SobrietyMilestoneController::class, 'index']);
    Route::post('sobriety-milestones', [SobrietyMilestoneController::class, 'store']);
    Route::get('sobriety-milestones/summary', [SobrietyMilestoneController::class, 'summary']);
    Route::post('sobriety-milestones/generate-defaults', [SobrietyMilestoneController::class, 'generateDefaults']);
    Route::post('sobriety-milestones/{id}/mark-achieved', [SobrietyMilestoneController::class, 'markAchieved']);
    Route::get('sobriety-milestones/{id}', [SobrietyMilestoneController::class, 'show']);
    Route::put('sobriety-milestones/{id}', [SobrietyMilestoneController::class, 'update']);
    Route::delete('sobriety-milestones/{id}', [SobrietyMilestoneController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Recovery Goal Routes
    |--------------------------------------------------------------------------
    */

    Route::get('recovery-goals', [RecoveryGoalController::class, 'index']);
    Route::post('recovery-goals', [RecoveryGoalController::class, 'store']);
    Route::get('recovery-goals/summary', [RecoveryGoalController::class, 'summary']);
    Route::post('recovery-goals/{id}/mark-completed', [RecoveryGoalController::class, 'markCompleted']);
    Route::patch('recovery-goals/{id}/progress', [RecoveryGoalController::class, 'updateProgress']);
    Route::get('recovery-goals/{id}', [RecoveryGoalController::class, 'show']);
    Route::put('recovery-goals/{id}', [RecoveryGoalController::class, 'update']);
    Route::delete('recovery-goals/{id}', [RecoveryGoalController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | AI Assistant Routes
    |--------------------------------------------------------------------------
    */

    Route::get('ai-assistant/assistants', [AiAssistantController::class, 'assistants']);

    Route::get('ai-assistant/sessions', [AiAssistantController::class, 'index']);
    Route::post('ai-assistant/sessions', [AiAssistantController::class, 'startSession']);
    Route::get('ai-assistant/summary', [AiAssistantController::class, 'summary']);
    Route::get('ai-assistant/sessions/{id}', [AiAssistantController::class, 'showSession']);
    Route::post('ai-assistant/sessions/{id}/message', [AiAssistantController::class, 'sendMessage']);
    Route::post('ai-assistant/sessions/{id}/close', [AiAssistantController::class, 'closeSession']);
    Route::delete('ai-assistant/sessions/{id}', [AiAssistantController::class, 'destroySession']);

    /*
    |--------------------------------------------------------------------------
    | Community Routes
    |--------------------------------------------------------------------------
    */

    Route::get('community/summary', [CommunityController::class, 'summary']);

    Route::get('community/groups', [CommunityController::class, 'groups']);
    Route::post('community/groups', [CommunityController::class, 'storeGroup']);
    Route::get('community/groups/{id}', [CommunityController::class, 'showGroup']);
    Route::put('community/groups/{id}', [CommunityController::class, 'updateGroup']);
    Route::delete('community/groups/{id}', [CommunityController::class, 'deleteGroup']);

    Route::get('community/posts', [CommunityController::class, 'posts']);
    Route::post('community/posts', [CommunityController::class, 'storePost']);
    Route::get('community/posts/{id}', [CommunityController::class, 'showPost']);
    Route::put('community/posts/{id}', [CommunityController::class, 'updatePost']);
    Route::delete('community/posts/{id}', [CommunityController::class, 'deletePost']);
    Route::post('community/posts/{id}/support', [CommunityController::class, 'supportPost']);

    Route::get('community/posts/{postId}/comments', [CommunityController::class, 'comments']);
    Route::post('community/posts/{postId}/comments', [CommunityController::class, 'storeComment']);
    Route::put('community/comments/{id}', [CommunityController::class, 'updateComment']);
    Route::delete('community/comments/{id}', [CommunityController::class, 'deleteComment']);

    Route::post('community/reports', [CommunityController::class, 'reportContent']);
    Route::get('community/reports', [CommunityController::class, 'reports']);
    Route::put('community/reports/{id}', [CommunityController::class, 'updateReport']);

    /*
    |--------------------------------------------------------------------------
    | Awareness / Campaign / Resource Routes
    |--------------------------------------------------------------------------
    */

    Route::get('awareness/summary', [AwarenessController::class, 'summary']);

    Route::get('awareness/categories', [AwarenessController::class, 'categories']);
    Route::post('awareness/categories', [AwarenessController::class, 'storeCategory']);
    Route::put('awareness/categories/{id}', [AwarenessController::class, 'updateCategory']);
    Route::delete('awareness/categories/{id}', [AwarenessController::class, 'deleteCategory']);

    Route::get('awareness/campaigns', [AwarenessController::class, 'campaigns']);
    Route::post('awareness/campaigns', [AwarenessController::class, 'storeCampaign']);
    Route::get('awareness/campaigns/{id}', [AwarenessController::class, 'showCampaign']);
    Route::put('awareness/campaigns/{id}', [AwarenessController::class, 'updateCampaign']);
    Route::delete('awareness/campaigns/{id}', [AwarenessController::class, 'deleteCampaign']);

    Route::post('awareness/campaigns/{campaignId}/contents', [AwarenessController::class, 'storeCampaignContent']);
    Route::put('awareness/campaign-contents/{id}', [AwarenessController::class, 'updateCampaignContent']);
    Route::delete('awareness/campaign-contents/{id}', [AwarenessController::class, 'deleteCampaignContent']);

    Route::get('awareness/resources', [AwarenessController::class, 'resources']);
    Route::post('awareness/resources', [AwarenessController::class, 'storeResource']);
    Route::get('awareness/resources/{id}', [AwarenessController::class, 'showResource']);
    Route::put('awareness/resources/{id}', [AwarenessController::class, 'updateResource']);
    Route::delete('awareness/resources/{id}', [AwarenessController::class, 'deleteResource']);

    /*
    |--------------------------------------------------------------------------
    | Notification / Reminder Routes
    |--------------------------------------------------------------------------
    */

    Route::get('notifications/summary', [NotificationController::class, 'summary']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::get('notifications/{id}', [NotificationController::class, 'show']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

    Route::get('reminders', [NotificationController::class, 'reminders']);
    Route::post('reminders', [NotificationController::class, 'storeReminder']);
    Route::get('reminders/{id}', [NotificationController::class, 'showReminder']);
    Route::put('reminders/{id}', [NotificationController::class, 'updateReminder']);
    Route::delete('reminders/{id}', [NotificationController::class, 'deleteReminder']);

    Route::post('device-tokens', [NotificationController::class, 'registerDeviceToken']);
    Route::get('device-tokens', [NotificationController::class, 'myDeviceTokens']);
    Route::post('device-tokens/{id}/deactivate', [NotificationController::class, 'deactivateDeviceToken']);
});
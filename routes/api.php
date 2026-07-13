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
use App\Http\Controllers\API\PatientConditionController;
use App\Http\Controllers\API\ReportController;

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

    Route::prefix('mood-logs')->group(function () {
        Route::get('/', [MoodLogController::class, 'index']);
        Route::post('/', [MoodLogController::class, 'store']);

        Route::get('today', [MoodLogController::class, 'today']);
        Route::get('summary', [MoodLogController::class, 'summary']);

        Route::get('{id}', [MoodLogController::class, 'show']);
        Route::put('{id}', [MoodLogController::class, 'update']);
        Route::delete('{id}', [MoodLogController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Trigger Log Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('trigger-logs')->group(function () {
        Route::get('/', [TriggerLogController::class, 'index']);
        Route::post('/', [TriggerLogController::class, 'store']);

        Route::get('summary', [TriggerLogController::class, 'summary']);

        Route::get('{id}', [TriggerLogController::class, 'show']);
        Route::put('{id}', [TriggerLogController::class, 'update']);
        Route::delete('{id}', [TriggerLogController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sobriety Milestone Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('sobriety-milestones')->group(function () {
        Route::get('/', [SobrietyMilestoneController::class, 'index']);
        Route::post('/', [SobrietyMilestoneController::class, 'store']);

        Route::get('summary', [SobrietyMilestoneController::class, 'summary']);
        Route::post(
            'generate-defaults',
            [SobrietyMilestoneController::class, 'generateDefaults']
        );

        Route::post(
            '{id}/mark-achieved',
            [SobrietyMilestoneController::class, 'markAchieved']
        );

        Route::get('{id}', [SobrietyMilestoneController::class, 'show']);
        Route::put('{id}', [SobrietyMilestoneController::class, 'update']);
        Route::delete('{id}', [SobrietyMilestoneController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Recovery Goal Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('recovery-goals')->group(function () {
        Route::get('/', [RecoveryGoalController::class, 'index']);
        Route::post('/', [RecoveryGoalController::class, 'store']);

        Route::get('summary', [RecoveryGoalController::class, 'summary']);

        Route::post(
            '{id}/mark-completed',
            [RecoveryGoalController::class, 'markCompleted']
        );

        Route::patch(
            '{id}/progress',
            [RecoveryGoalController::class, 'updateProgress']
        );

        Route::get('{id}', [RecoveryGoalController::class, 'show']);
        Route::put('{id}', [RecoveryGoalController::class, 'update']);
        Route::delete('{id}', [RecoveryGoalController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | AI Assistant Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('ai-assistant')->group(function () {
        Route::get(
            'assistants',
            [AiAssistantController::class, 'assistants']
        );

        Route::get(
            'sessions',
            [AiAssistantController::class, 'index']
        );

        Route::post(
            'sessions',
            [AiAssistantController::class, 'startSession']
        );

        Route::get(
            'summary',
            [AiAssistantController::class, 'summary']
        );

        Route::get(
            'sessions/{id}',
            [AiAssistantController::class, 'showSession']
        );

        Route::post(
            'sessions/{id}/message',
            [AiAssistantController::class, 'sendMessage']
        );

        Route::post(
            'sessions/{id}/close',
            [AiAssistantController::class, 'closeSession']
        );

        Route::delete(
            'sessions/{id}',
            [AiAssistantController::class, 'destroySession']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Community Routes
    |--------------------------------------------------------------------------
    |
    | Admin or moderator can create and manage community groups.
    | Mobile users can view active groups and publish support posts.
    |
    */

    Route::prefix('community')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Community Summary
        |--------------------------------------------------------------------------
        */

        Route::get(
            'summary',
            [CommunityController::class, 'summary']
        );

        /*
        |--------------------------------------------------------------------------
        | Community Group Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'groups',
            [CommunityController::class, 'groups']
        );

        Route::post(
            'groups',
            [CommunityController::class, 'storeGroup']
        );

        Route::get(
            'groups/{id}',
            [CommunityController::class, 'showGroup']
        );

        Route::put(
            'groups/{id}',
            [CommunityController::class, 'updateGroup']
        );

        Route::patch(
            'groups/{id}',
            [CommunityController::class, 'updateGroup']
        );

        Route::delete(
            'groups/{id}',
            [CommunityController::class, 'deleteGroup']
        );

        /*
        |--------------------------------------------------------------------------
        | Community Post Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'posts',
            [CommunityController::class, 'posts']
        );

        Route::post(
            'posts',
            [CommunityController::class, 'storePost']
        );

        Route::get(
            'posts/{id}',
            [CommunityController::class, 'showPost']
        );

        Route::put(
            'posts/{id}',
            [CommunityController::class, 'updatePost']
        );

        Route::patch(
            'posts/{id}',
            [CommunityController::class, 'updatePost']
        );

        Route::delete(
            'posts/{id}',
            [CommunityController::class, 'deletePost']
        );

        Route::post(
            'posts/{id}/support',
            [CommunityController::class, 'supportPost']
        );

        /*
        |--------------------------------------------------------------------------
        | Community Comment Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'posts/{postId}/comments',
            [CommunityController::class, 'comments']
        );

        Route::post(
            'posts/{postId}/comments',
            [CommunityController::class, 'storeComment']
        );

        Route::put(
            'comments/{id}',
            [CommunityController::class, 'updateComment']
        );

        Route::patch(
            'comments/{id}',
            [CommunityController::class, 'updateComment']
        );

        Route::delete(
            'comments/{id}',
            [CommunityController::class, 'deleteComment']
        );

        /*
        |--------------------------------------------------------------------------
        | Community Content Report Routes
        |--------------------------------------------------------------------------
        */

        Route::post(
            'reports',
            [CommunityController::class, 'reportContent']
        );

        Route::get(
            'reports',
            [CommunityController::class, 'reports']
        );

        Route::put(
            'reports/{id}',
            [CommunityController::class, 'updateReport']
        );

        Route::patch(
            'reports/{id}',
            [CommunityController::class, 'updateReport']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Awareness / Campaign / Resource Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('awareness')->group(function () {

        Route::get(
            'summary',
            [AwarenessController::class, 'summary']
        );

        /*
        |--------------------------------------------------------------------------
        | Awareness Category Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'categories',
            [AwarenessController::class, 'categories']
        );

        Route::post(
            'categories',
            [AwarenessController::class, 'storeCategory']
        );

        Route::put(
            'categories/{id}',
            [AwarenessController::class, 'updateCategory']
        );

        Route::patch(
            'categories/{id}',
            [AwarenessController::class, 'updateCategory']
        );

        Route::delete(
            'categories/{id}',
            [AwarenessController::class, 'deleteCategory']
        );

        /*
        |--------------------------------------------------------------------------
        | Awareness Campaign Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'campaigns',
            [AwarenessController::class, 'campaigns']
        );

        Route::post(
            'campaigns',
            [AwarenessController::class, 'storeCampaign']
        );

        Route::get(
            'campaigns/{id}',
            [AwarenessController::class, 'showCampaign']
        );

        Route::put(
            'campaigns/{id}',
            [AwarenessController::class, 'updateCampaign']
        );

        Route::patch(
            'campaigns/{id}',
            [AwarenessController::class, 'updateCampaign']
        );

        Route::delete(
            'campaigns/{id}',
            [AwarenessController::class, 'deleteCampaign']
        );

        /*
        |--------------------------------------------------------------------------
        | Awareness Campaign Content Routes
        |--------------------------------------------------------------------------
        */

        Route::post(
            'campaigns/{campaignId}/contents',
            [AwarenessController::class, 'storeCampaignContent']
        );

        Route::put(
            'campaign-contents/{id}',
            [AwarenessController::class, 'updateCampaignContent']
        );

        Route::patch(
            'campaign-contents/{id}',
            [AwarenessController::class, 'updateCampaignContent']
        );

        Route::delete(
            'campaign-contents/{id}',
            [AwarenessController::class, 'deleteCampaignContent']
        );

        /*
        |--------------------------------------------------------------------------
        | Awareness Resource Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'resources',
            [AwarenessController::class, 'resources']
        );

        Route::post(
            'resources',
            [AwarenessController::class, 'storeResource']
        );

        Route::get(
            'resources/{id}',
            [AwarenessController::class, 'showResource']
        );

        Route::put(
            'resources/{id}',
            [AwarenessController::class, 'updateResource']
        );

        Route::patch(
            'resources/{id}',
            [AwarenessController::class, 'updateResource']
        );

        Route::delete(
            'resources/{id}',
            [AwarenessController::class, 'deleteResource']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Patient Condition Routes
    |--------------------------------------------------------------------------
    |
    | These records connect patients to addiction and illness information.
    | They are used by the reporting module to filter patients by:
    |
    | - Addiction type
    | - Illness or diagnosis
    | - Severity
    | - Treatment status
    | - Care status
    |
    */

    Route::prefix('patient-conditions')->group(function () {
        Route::get(
            '/',
            [PatientConditionController::class, 'index']
        );

        Route::post(
            '/',
            [PatientConditionController::class, 'store']
        );

        Route::put(
            '{condition}',
            [PatientConditionController::class, 'update']
        );

        Route::patch(
            '{condition}',
            [PatientConditionController::class, 'update']
        );

        Route::delete(
            '{condition}',
            [PatientConditionController::class, 'destroy']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Professional Reporting Routes
    |--------------------------------------------------------------------------
    |
    | Supported reports:
    |
    | - Patients by specific addiction
    | - Full recovery award eligibility
    | - Illness and diagnosis report
    | - Daily system usage report
    | - Recovery progress report
    | - Relapse-risk report
    | - Professional PDF export
    |
    */

    Route::prefix('reports')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Report Filters
        |--------------------------------------------------------------------------
        |
        | Returns available users, addiction types, illnesses, severities,
        | statuses and other filter options required by the report page.
        |
        */

        Route::get(
            'filters',
            [ReportController::class, 'filters']
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Report Data
        |--------------------------------------------------------------------------
        |
        | Examples:
        |
        | GET /api/reports/generate?report_type=addiction_patients
        | GET /api/reports/generate?report_type=full_recovery_awards
        | GET /api/reports/generate?report_type=illness
        | GET /api/reports/generate?report_type=daily_usage
        | GET /api/reports/generate?report_type=recovery_progress
        | GET /api/reports/generate?report_type=relapse_risk
        |
        */

        Route::get(
            'generate',
            [ReportController::class, 'generate']
        );

        /*
        |--------------------------------------------------------------------------
        | Export Professional PDF
        |--------------------------------------------------------------------------
        */

        Route::get(
            'pdf',
            [ReportController::class, 'exportPdf']
        );

        /*
        |--------------------------------------------------------------------------
        | Full Recovery Awards
        |--------------------------------------------------------------------------
        */

        Route::post(
            'full-recovery-awards',
            [ReportController::class, 'awardFullRecovery']
        );

        Route::patch(
            'full-recovery-awards/{award}/revoke',
            [ReportController::class, 'revokeAward']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Notification Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function () {
        Route::get(
            'summary',
            [NotificationController::class, 'summary']
        );

        Route::post(
            'mark-all-read',
            [NotificationController::class, 'markAllAsRead']
        );

        Route::get(
            '/',
            [NotificationController::class, 'index']
        );

        Route::post(
            '/',
            [NotificationController::class, 'store']
        );

        Route::get(
            '{id}',
            [NotificationController::class, 'show']
        );

        Route::post(
            '{id}/read',
            [NotificationController::class, 'markAsRead']
        );

        Route::delete(
            '{id}',
            [NotificationController::class, 'destroy']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Reminder Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('reminders')->group(function () {
        Route::get(
            '/',
            [NotificationController::class, 'reminders']
        );

        Route::post(
            '/',
            [NotificationController::class, 'storeReminder']
        );

        Route::get(
            '{id}',
            [NotificationController::class, 'showReminder']
        );

        Route::put(
            '{id}',
            [NotificationController::class, 'updateReminder']
        );

        Route::patch(
            '{id}',
            [NotificationController::class, 'updateReminder']
        );

        Route::delete(
            '{id}',
            [NotificationController::class, 'deleteReminder']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Device Token Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('device-tokens')->group(function () {
        Route::post(
            '/',
            [NotificationController::class, 'registerDeviceToken']
        );

        Route::get(
            '/',
            [NotificationController::class, 'myDeviceTokens']
        );

        Route::post(
            '{id}/deactivate',
            [NotificationController::class, 'deactivateDeviceToken']
        );
    });
});
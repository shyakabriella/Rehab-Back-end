<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NotificationController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | Device Tokens
    |--------------------------------------------------------------------------
    */

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'platform' => 'nullable|in:android,ios,web,unknown',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $token = DeviceToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_token' => $request->device_token,
            ],
            [
                'platform' => $request->platform ?? 'unknown',
                'device_name' => $request->device_name,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return $this->sendResponse($token, 'Device token registered successfully.');
    }

    public function myDeviceTokens(Request $request): JsonResponse
    {
        $tokens = DeviceToken::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->sendResponse($tokens, 'Device tokens fetched successfully.');
    }

    public function deactivateDeviceToken(Request $request, int $id): JsonResponse
    {
        $token = DeviceToken::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$token) {
            return $this->sendError('Device token not found.', [
                'error' => 'This device token does not exist or does not belong to this user.'
            ]);
        }

        $token->update([
            'is_active' => false,
        ]);

        return $this->sendResponse($token, 'Device token deactivated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $query = AppNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('read')) {
            if ($request->boolean('read')) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->paginate(20);

        return $this->sendResponse($notifications, 'Notifications fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin() && !$request->user()->isModerator()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create notifications.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'type' => 'nullable|in:daily_checkin,motivation,milestone,goal,campaign,ai_followup,community,system',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,sent,failed,cancelled',
            'data' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $notification = AppNotification::create([
            'user_id' => $request->user_id,
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'system',
            'priority' => $request->priority ?? 'medium',
            'status' => $request->status ?? 'pending',
            'data' => $request->data,
            'scheduled_at' => $request->scheduled_at,
            'sent_at' => ($request->status === 'sent') ? now() : null,
        ]);

        return $this->sendResponse($notification, 'Notification created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found.', [
                'error' => 'This notification does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($notification, 'Notification fetched successfully.');
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found.', [
                'error' => 'This notification does not exist or does not belong to this user.'
            ]);
        }

        $notification->update([
            'read_at' => now(),
        ]);

        return $this->sendResponse($notification, 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return $this->sendResponse([], 'All notifications marked as read.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found.', [
                'error' => 'This notification does not exist or does not belong to this user.'
            ]);
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    */

    public function reminders(Request $request): JsonResponse
    {
        $query = Reminder::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reminder_type')) {
            $query->where('reminder_type', $request->reminder_type);
        }

        $reminders = $query->paginate(20);

        return $this->sendResponse($reminders, 'Reminders fetched successfully.');
    }

    public function storeReminder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000',
            'reminder_type' => 'nullable|in:mood_checkin,goal_progress,sobriety_milestone,ai_followup,campaign_alert,custom',
            'frequency' => 'nullable|in:once,daily,weekly,monthly',
            'remind_time' => 'nullable|date_format:H:i',
            'remind_date' => 'nullable|date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'nullable|in:active,paused,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $nextTriggerAt = $this->calculateNextTriggerAt(
            $request->remind_date,
            $request->remind_time,
            $request->frequency ?? 'once'
        );

        $reminder = Reminder::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'reminder_type' => $request->reminder_type ?? 'custom',
            'frequency' => $request->frequency ?? 'once',
            'remind_time' => $request->remind_time,
            'remind_date' => $request->remind_date,
            'days_of_week' => $request->days_of_week,
            'status' => $request->status ?? 'active',
            'next_trigger_at' => $nextTriggerAt,
        ]);

        return $this->sendResponse($reminder, 'Reminder created successfully.');
    }

    public function showReminder(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$reminder) {
            return $this->sendError('Reminder not found.', [
                'error' => 'This reminder does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($reminder, 'Reminder fetched successfully.');
    }

    public function updateReminder(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$reminder) {
            return $this->sendError('Reminder not found.', [
                'error' => 'This reminder does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:3000',
            'reminder_type' => 'nullable|in:mood_checkin,goal_progress,sobriety_milestone,ai_followup,campaign_alert,custom',
            'frequency' => 'nullable|in:once,daily,weekly,monthly',
            'remind_time' => 'nullable|date_format:H:i',
            'remind_date' => 'nullable|date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'nullable|in:active,paused,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'title',
            'description',
            'reminder_type',
            'frequency',
            'remind_time',
            'remind_date',
            'days_of_week',
            'status',
        ]);

        if ($request->filled('remind_date') || $request->filled('remind_time') || $request->filled('frequency')) {
            $data['next_trigger_at'] = $this->calculateNextTriggerAt(
                $request->remind_date ?? optional($reminder->remind_date)->format('Y-m-d'),
                $request->remind_time ?? $reminder->remind_time,
                $request->frequency ?? $reminder->frequency
            );
        }

        $reminder->update($data);

        return $this->sendResponse($reminder, 'Reminder updated successfully.');
    }

    public function deleteReminder(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$reminder) {
            return $this->sendError('Reminder not found.', [
                'error' => 'This reminder does not exist or does not belong to this user.'
            ]);
        }

        $reminder->delete();

        return $this->sendResponse([], 'Reminder deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = [
            'total_notifications' => AppNotification::where('user_id', $userId)->count(),
            'unread_notifications' => AppNotification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count(),
            'pending_notifications' => AppNotification::where('user_id', $userId)
                ->where('status', 'pending')
                ->count(),
            'sent_notifications' => AppNotification::where('user_id', $userId)
                ->where('status', 'sent')
                ->count(),
            'active_reminders' => Reminder::where('user_id', $userId)
                ->where('status', 'active')
                ->count(),
            'total_reminders' => Reminder::where('user_id', $userId)->count(),
            'active_device_tokens' => DeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->count(),
            'latest_notification' => AppNotification::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first(),
            'next_reminder' => Reminder::where('user_id', $userId)
                ->where('status', 'active')
                ->whereNotNull('next_trigger_at')
                ->orderBy('next_trigger_at')
                ->first(),
        ];

        return $this->sendResponse($data, 'Notification summary fetched successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function calculateNextTriggerAt(?string $date, ?string $time, string $frequency): ?Carbon
    {
        if (!$date && !$time) {
            return null;
        }

        $baseDate = $date ?: now()->toDateString();
        $baseTime = $time ?: '08:00';

        $next = Carbon::parse($baseDate . ' ' . $baseTime);

        if ($next->isPast()) {
            if ($frequency === 'daily') {
                $next = now()->setTimeFromTimeString($baseTime)->addDay();
            } elseif ($frequency === 'weekly') {
                $next = now()->setTimeFromTimeString($baseTime)->addWeek();
            } elseif ($frequency === 'monthly') {
                $next = now()->setTimeFromTimeString($baseTime)->addMonth();
            }
        }

        return $next;
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\RecoveryGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecoveryGoalController extends BaseController
{
    /**
     * Get all recovery goals for logged-in user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RecoveryGoal::where('user_id', $request->user()->id)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $goals = $query->paginate(15);

        return $this->sendResponse($goals, 'Recovery goals fetched successfully.');
    }

    /**
     * Create recovery goal.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000',
            'category' => [
                'nullable',
                Rule::in([
                    'sobriety',
                    'health',
                    'mental_health',
                    'community',
                    'habit',
                    'education',
                    'other',
                ]),
            ],
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:not_started,in_progress,completed,cancelled',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date|after_or_equal:start_date',
            'completed_date' => 'nullable|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $status = $request->status ?? 'not_started';
        $progress = $request->progress_percent ?? 0;

        if ($status === 'completed') {
            $progress = 100;
        }

        $goal = RecoveryGoal::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category ?? 'other',
            'priority' => $request->priority ?? 'medium',
            'status' => $status,
            'progress_percent' => $progress,
            'start_date' => $request->start_date,
            'target_date' => $request->target_date,
            'completed_date' => $status === 'completed'
                ? ($request->completed_date ?? now()->toDateString())
                : $request->completed_date,
            'notes' => $request->notes,
        ]);

        return $this->sendResponse($goal, 'Recovery goal created successfully.');
    }

    /**
     * Get one recovery goal.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $goal = RecoveryGoal::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$goal) {
            return $this->sendError('Recovery goal not found.', [
                'error' => 'This recovery goal does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($goal, 'Recovery goal fetched successfully.');
    }

    /**
     * Update recovery goal.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $goal = RecoveryGoal::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$goal) {
            return $this->sendError('Recovery goal not found.', [
                'error' => 'This recovery goal does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:3000',
            'category' => [
                'nullable',
                Rule::in([
                    'sobriety',
                    'health',
                    'mental_health',
                    'community',
                    'habit',
                    'education',
                    'other',
                ]),
            ],
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:not_started,in_progress,completed,cancelled',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date|after_or_equal:start_date',
            'completed_date' => 'nullable|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'title',
            'description',
            'category',
            'priority',
            'status',
            'progress_percent',
            'start_date',
            'target_date',
            'completed_date',
            'notes',
        ]);

        if ($request->status === 'completed') {
            $data['progress_percent'] = 100;
            $data['completed_date'] = $request->completed_date ?? now()->toDateString();
        }

        if ($request->filled('progress_percent') && (int) $request->progress_percent === 100) {
            $data['status'] = 'completed';
            $data['completed_date'] = $request->completed_date ?? now()->toDateString();
        }

        $goal->update($data);

        return $this->sendResponse($goal, 'Recovery goal updated successfully.');
    }

    /**
     * Delete recovery goal.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $goal = RecoveryGoal::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$goal) {
            return $this->sendError('Recovery goal not found.', [
                'error' => 'This recovery goal does not exist or does not belong to this user.'
            ]);
        }

        $goal->delete();

        return $this->sendResponse([], 'Recovery goal deleted successfully.');
    }

    /**
     * Recovery goals summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalGoals = RecoveryGoal::where('user_id', $userId)->count();

        $notStartedGoals = RecoveryGoal::where('user_id', $userId)
            ->where('status', 'not_started')
            ->count();

        $inProgressGoals = RecoveryGoal::where('user_id', $userId)
            ->where('status', 'in_progress')
            ->count();

        $completedGoals = RecoveryGoal::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        $cancelledGoals = RecoveryGoal::where('user_id', $userId)
            ->where('status', 'cancelled')
            ->count();

        $highPriorityGoals = RecoveryGoal::where('user_id', $userId)
            ->where('priority', 'high')
            ->count();

        $averageProgress = RecoveryGoal::where('user_id', $userId)
            ->avg('progress_percent');

        $latestGoal = RecoveryGoal::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        $nextTargetGoal = RecoveryGoal::where('user_id', $userId)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->whereNotNull('target_date')
            ->orderBy('target_date')
            ->first();

        $data = [
            'total_goals' => $totalGoals,
            'not_started_goals' => $notStartedGoals,
            'in_progress_goals' => $inProgressGoals,
            'completed_goals' => $completedGoals,
            'cancelled_goals' => $cancelledGoals,
            'high_priority_goals' => $highPriorityGoals,
            'average_progress_percent' => $averageProgress ? round($averageProgress, 2) : 0,
            'latest_goal' => $latestGoal,
            'next_target_goal' => $nextTargetGoal,
        ];

        return $this->sendResponse($data, 'Recovery goals summary fetched successfully.');
    }

    /**
     * Mark goal as completed.
     */
    public function markCompleted(Request $request, int $id): JsonResponse
    {
        $goal = RecoveryGoal::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$goal) {
            return $this->sendError('Recovery goal not found.', [
                'error' => 'This recovery goal does not exist or does not belong to this user.'
            ]);
        }

        $goal->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_date' => now()->toDateString(),
        ]);

        return $this->sendResponse($goal, 'Recovery goal marked as completed.');
    }

    /**
     * Update goal progress only.
     */
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $goal = RecoveryGoal::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$goal) {
            return $this->sendError('Recovery goal not found.', [
                'error' => 'This recovery goal does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'progress_percent' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = [
            'progress_percent' => $request->progress_percent,
        ];

        if ($request->filled('notes')) {
            $data['notes'] = $request->notes;
        }

        if ((int) $request->progress_percent === 100) {
            $data['status'] = 'completed';
            $data['completed_date'] = now()->toDateString();
        } elseif ((int) $request->progress_percent > 0) {
            $data['status'] = 'in_progress';
            $data['completed_date'] = null;
        } else {
            $data['status'] = 'not_started';
            $data['completed_date'] = null;
        }

        $goal->update($data);

        return $this->sendResponse($goal, 'Recovery goal progress updated successfully.');
    }
}
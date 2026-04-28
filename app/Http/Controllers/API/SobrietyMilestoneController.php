<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\SobrietyMilestone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SobrietyMilestoneController extends BaseController
{
    /**
     * Get all sobriety milestones for logged-in user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SobrietyMilestone::where('user_id', $request->user()->id)
            ->orderByDesc('milestone_days')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('milestone_type')) {
            $query->where('milestone_type', $request->milestone_type);
        }

        $milestones = $query->paginate(15);

        return $this->sendResponse($milestones, 'Sobriety milestones fetched successfully.');
    }

    /**
     * Create sobriety milestone.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'milestone_type' => [
                'nullable',
                Rule::in([
                    'first_day',
                    'one_week',
                    'two_weeks',
                    'one_month',
                    'three_months',
                    'six_months',
                    'one_year',
                    'custom',
                ]),
            ],
            'milestone_days' => 'required|integer|min:1|max:10000',
            'achieved_date' => 'nullable|date',
            'status' => 'nullable|in:in_progress,achieved,missed,reset',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $status = $request->status ?? 'in_progress';

        $milestone = SobrietyMilestone::create([
            'user_id' => $request->user()->id,
            'milestone_type' => $request->milestone_type ?? 'custom',
            'milestone_days' => $request->milestone_days,
            'achieved_date' => $status === 'achieved'
                ? ($request->achieved_date ?? now()->toDateString())
                : $request->achieved_date,
            'status' => $status,
            'title' => $request->title,
            'notes' => $request->notes,
        ]);

        return $this->sendResponse($milestone, 'Sobriety milestone created successfully.');
    }

    /**
     * Get one sobriety milestone.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $milestone = SobrietyMilestone::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$milestone) {
            return $this->sendError('Sobriety milestone not found.', [
                'error' => 'This milestone does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($milestone, 'Sobriety milestone fetched successfully.');
    }

    /**
     * Update sobriety milestone.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $milestone = SobrietyMilestone::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$milestone) {
            return $this->sendError('Sobriety milestone not found.', [
                'error' => 'This milestone does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'milestone_type' => [
                'nullable',
                Rule::in([
                    'first_day',
                    'one_week',
                    'two_weeks',
                    'one_month',
                    'three_months',
                    'six_months',
                    'one_year',
                    'custom',
                ]),
            ],
            'milestone_days' => 'nullable|integer|min:1|max:10000',
            'achieved_date' => 'nullable|date',
            'status' => 'nullable|in:in_progress,achieved,missed,reset',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'milestone_type',
            'milestone_days',
            'achieved_date',
            'status',
            'title',
            'notes',
        ]);

        if (($request->status === 'achieved') && empty($request->achieved_date)) {
            $data['achieved_date'] = now()->toDateString();
        }

        $milestone->update($data);

        return $this->sendResponse($milestone, 'Sobriety milestone updated successfully.');
    }

    /**
     * Delete sobriety milestone.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $milestone = SobrietyMilestone::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$milestone) {
            return $this->sendError('Sobriety milestone not found.', [
                'error' => 'This milestone does not exist or does not belong to this user.'
            ]);
        }

        $milestone->delete();

        return $this->sendResponse([], 'Sobriety milestone deleted successfully.');
    }

    /**
     * Sobriety summary for mobile/dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        $recoveryStartDate = optional($user->profile)->recovery_start_date;

        $soberDays = 0;

        if ($recoveryStartDate) {
            $soberDays = Carbon::parse($recoveryStartDate)
                ->startOfDay()
                ->diffInDays(now()->startOfDay()) + 1;
        }

        $totalMilestones = SobrietyMilestone::where('user_id', $user->id)->count();

        $achievedMilestones = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'achieved')
            ->count();

        $inProgressMilestones = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->count();

        $resetCount = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'reset')
            ->count();

        $latestMilestone = SobrietyMilestone::where('user_id', $user->id)
            ->orderByDesc('achieved_date')
            ->orderByDesc('created_at')
            ->first();

        $nextMilestone = SobrietyMilestone::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where('milestone_days', '>', $soberDays)
            ->orderBy('milestone_days')
            ->first();

        $data = [
            'recovery_start_date' => $recoveryStartDate
                ? Carbon::parse($recoveryStartDate)->format('Y-m-d')
                : null,
            'current_sober_days' => $soberDays,
            'total_milestones' => $totalMilestones,
            'achieved_milestones' => $achievedMilestones,
            'in_progress_milestones' => $inProgressMilestones,
            'reset_count' => $resetCount,
            'latest_milestone' => $latestMilestone,
            'next_milestone' => $nextMilestone,
        ];

        return $this->sendResponse($data, 'Sobriety summary fetched successfully.');
    }

    /**
     * Generate default milestones for user.
     */
    public function generateDefaults(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $defaults = [
            [
                'milestone_type' => 'first_day',
                'milestone_days' => 1,
                'title' => 'First Day Sober',
            ],
            [
                'milestone_type' => 'one_week',
                'milestone_days' => 7,
                'title' => 'One Week Sober',
            ],
            [
                'milestone_type' => 'two_weeks',
                'milestone_days' => 14,
                'title' => 'Two Weeks Sober',
            ],
            [
                'milestone_type' => 'one_month',
                'milestone_days' => 30,
                'title' => 'One Month Sober',
            ],
            [
                'milestone_type' => 'three_months',
                'milestone_days' => 90,
                'title' => 'Three Months Sober',
            ],
            [
                'milestone_type' => 'six_months',
                'milestone_days' => 180,
                'title' => 'Six Months Sober',
            ],
            [
                'milestone_type' => 'one_year',
                'milestone_days' => 365,
                'title' => 'One Year Sober',
            ],
        ];

        foreach ($defaults as $item) {
            SobrietyMilestone::firstOrCreate(
                [
                    'user_id' => $userId,
                    'milestone_type' => $item['milestone_type'],
                    'milestone_days' => $item['milestone_days'],
                ],
                [
                    'title' => $item['title'],
                    'status' => 'in_progress',
                    'notes' => null,
                ]
            );
        }

        $milestones = SobrietyMilestone::where('user_id', $userId)
            ->orderBy('milestone_days')
            ->get();

        return $this->sendResponse($milestones, 'Default sobriety milestones generated successfully.');
    }

    /**
     * Mark milestone as achieved.
     */
    public function markAchieved(Request $request, int $id): JsonResponse
    {
        $milestone = SobrietyMilestone::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$milestone) {
            return $this->sendError('Sobriety milestone not found.', [
                'error' => 'This milestone does not exist or does not belong to this user.'
            ]);
        }

        $milestone->update([
            'status' => 'achieved',
            'achieved_date' => now()->toDateString(),
        ]);

        return $this->sendResponse($milestone, 'Sobriety milestone marked as achieved.');
    }
}
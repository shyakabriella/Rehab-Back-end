<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\MoodLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MoodLogController extends BaseController
{
    /**
     * Get all mood logs for logged-in user
     */
    public function index(Request $request): JsonResponse
    {
        $query = MoodLog::where('user_id', $request->user()->id)
            ->orderByDesc('logged_date');

        if ($request->filled('date_from')) {
            $query->whereDate('logged_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('logged_date', '<=', $request->date_to);
        }

        $moodLogs = $query->paginate(15);

        return $this->sendResponse($moodLogs, 'Mood logs fetched successfully.');
    }

    /**
     * Create daily mood log
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $loggedDate = $request->logged_date ?? now()->toDateString();

        $validator = Validator::make(array_merge($request->all(), [
            'logged_date' => $loggedDate,
        ]), [
            'mood' => [
                'required',
                Rule::in([
                    'happy',
                    'sad',
                    'stressed',
                    'anxious',
                    'angry',
                    'hopeful',
                    'tired',
                    'calm',
                    'lonely',
                    'motivated'
                ]),
            ],
            'stress_level' => 'nullable|integer|min:0|max:10',
            'craving_level' => 'nullable|integer|min:0|max:10',
            'energy_level' => 'nullable|integer|min:0|max:10',
            'sleep_quality' => 'nullable|in:poor,fair,good,very_good',
            'had_craving' => 'nullable|boolean',
            'main_trigger' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:3000',
            'logged_date' => [
                'required',
                'date',
                Rule::unique('mood_logs', 'logged_date')
                    ->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $moodLog = MoodLog::create([
            'user_id' => $user->id,
            'mood' => $request->mood,
            'stress_level' => $request->stress_level,
            'craving_level' => $request->craving_level,
            'energy_level' => $request->energy_level,
            'sleep_quality' => $request->sleep_quality,
            'had_craving' => $request->boolean('had_craving'),
            'main_trigger' => $request->main_trigger,
            'notes' => $request->notes,
            'logged_date' => $loggedDate,
        ]);

        return $this->sendResponse($moodLog, 'Mood log created successfully.');
    }

    /**
     * Get one mood log
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $moodLog = MoodLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($moodLog, 'Mood log fetched successfully.');
    }

    /**
     * Update mood log
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $moodLog = MoodLog::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'mood' => [
                'nullable',
                Rule::in([
                    'happy',
                    'sad',
                    'stressed',
                    'anxious',
                    'angry',
                    'hopeful',
                    'tired',
                    'calm',
                    'lonely',
                    'motivated'
                ]),
            ],
            'stress_level' => 'nullable|integer|min:0|max:10',
            'craving_level' => 'nullable|integer|min:0|max:10',
            'energy_level' => 'nullable|integer|min:0|max:10',
            'sleep_quality' => 'nullable|in:poor,fair,good,very_good',
            'had_craving' => 'nullable|boolean',
            'main_trigger' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:3000',
            'logged_date' => [
                'nullable',
                'date',
                Rule::unique('mood_logs', 'logged_date')
                    ->where(fn ($query) => $query->where('user_id', $user->id))
                    ->ignore($moodLog->id),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $moodLog->update($request->only([
            'mood',
            'stress_level',
            'craving_level',
            'energy_level',
            'sleep_quality',
            'had_craving',
            'main_trigger',
            'notes',
            'logged_date',
        ]));

        return $this->sendResponse($moodLog, 'Mood log updated successfully.');
    }

    /**
     * Delete mood log
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $moodLog = MoodLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or does not belong to this user.'
            ]);
        }

        $moodLog->delete();

        return $this->sendResponse([], 'Mood log deleted successfully.');
    }

    /**
     * Get today's mood log
     */
    public function today(Request $request): JsonResponse
    {
        $moodLog = MoodLog::where('user_id', $request->user()->id)
            ->whereDate('logged_date', now()->toDateString())
            ->first();

        if (!$moodLog) {
            return $this->sendError('No mood log today.', [
                'error' => 'This user has not added mood log for today.'
            ]);
        }

        return $this->sendResponse($moodLog, 'Today mood log fetched successfully.');
    }

    /**
     * Simple mood summary for dashboard/mobile
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalLogs = MoodLog::where('user_id', $userId)->count();

        $latestLog = MoodLog::where('user_id', $userId)
            ->orderByDesc('logged_date')
            ->first();

        $averageStress = MoodLog::where('user_id', $userId)
            ->whereNotNull('stress_level')
            ->avg('stress_level');

        $averageCraving = MoodLog::where('user_id', $userId)
            ->whereNotNull('craving_level')
            ->avg('craving_level');

        $highCravingDays = MoodLog::where('user_id', $userId)
            ->where('craving_level', '>=', 7)
            ->count();

        $data = [
            'total_logs' => $totalLogs,
            'latest_log' => $latestLog,
            'average_stress_level' => $averageStress ? round($averageStress, 2) : 0,
            'average_craving_level' => $averageCraving ? round($averageCraving, 2) : 0,
            'high_craving_days' => $highCravingDays,
        ];

        return $this->sendResponse($data, 'Mood summary fetched successfully.');
    }
}
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
     * Check if authenticated user is admin.
     */
    private function canViewAllMoodLogs($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $roleName = optional($user->role)->name;

        return $roleName === 'admin';
    }

    /**
     * Get mood logs.
     *
     * Admin: can see all users' mood logs.
     * Normal user: can see only own mood logs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        $query = MoodLog::query()
            ->orderByDesc('logged_date')
            ->orderByDesc('created_at');

        if ($isAdmin) {
            /*
             * Admin can see all users.
             * Optional filter:
             * /api/mood-logs?user_id=5
             */
            $query->with('user:id,name,email');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('logged_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('logged_date', '<=', $request->date_to);
        }

        if ($request->filled('mood')) {
            $query->where('mood', $request->mood);
        }

        if ($request->filled('had_craving')) {
            $query->where('had_craving', $request->boolean('had_craving'));
        }

        $moodLogs = $query->paginate($request->integer('per_page', 15));

        return $this->sendResponse($moodLogs, 'Mood logs fetched successfully.');
    }

    /**
     * Create daily mood log.
     *
     * Normal users create logs for themselves.
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
                    'motivated',
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
     * Get one mood log.
     *
     * Admin: can see any mood log.
     * Normal user: can see only own mood log.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        $query = MoodLog::query()->where('id', $id);

        if ($isAdmin) {
            $query->with('user:id,name,email');
        } else {
            $query->where('user_id', $user->id);
        }

        $moodLog = $query->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or you do not have permission to view it.',
            ]);
        }

        return $this->sendResponse($moodLog, 'Mood log fetched successfully.');
    }

    /**
     * Update mood log.
     *
     * Kept owner-only for privacy.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $moodLog = MoodLog::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or does not belong to this user.',
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
                    'motivated',
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
     * Delete mood log.
     *
     * Kept owner-only for privacy.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $moodLog = MoodLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError('Mood log not found.', [
                'error' => 'This mood log does not exist or does not belong to this user.',
            ]);
        }

        $moodLog->delete();

        return $this->sendResponse([], 'Mood log deleted successfully.');
    }

    /**
     * Get today's mood log.
     *
     * Admin: sees all mood logs submitted today.
     * Normal user: sees own mood log for today.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        if ($isAdmin) {
            $query = MoodLog::with('user:id,name,email')
                ->whereDate('logged_date', now()->toDateString())
                ->orderByDesc('created_at');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $moodLogs = $query->paginate($request->integer('per_page', 15));

            return $this->sendResponse($moodLogs, 'Today mood logs fetched successfully.');
        }

        $moodLog = MoodLog::where('user_id', $user->id)
            ->whereDate('logged_date', now()->toDateString())
            ->first();

        if (!$moodLog) {
            return $this->sendError('No mood log today.', [
                'error' => 'This user has not added mood log for today.',
            ]);
        }

        return $this->sendResponse($moodLog, 'Today mood log fetched successfully.');
    }

    /**
     * Mood summary.
     *
     * Admin: sees summary for all users.
     * Normal user: sees own summary only.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        $query = MoodLog::query();

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('logged_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('logged_date', '<=', $request->date_to);
        }

        $totalLogs = (clone $query)->count();

        $latestLogQuery = clone $query;

        if ($isAdmin) {
            $latestLogQuery->with('user:id,name,email');
        }

        $latestLog = $latestLogQuery
            ->orderByDesc('logged_date')
            ->orderByDesc('created_at')
            ->first();

        $averageStress = (clone $query)
            ->whereNotNull('stress_level')
            ->avg('stress_level');

        $averageCraving = (clone $query)
            ->whereNotNull('craving_level')
            ->avg('craving_level');

        $averageEnergy = (clone $query)
            ->whereNotNull('energy_level')
            ->avg('energy_level');

        $highCravingDays = (clone $query)
            ->where('craving_level', '>=', 7)
            ->count();

        $totalHadCraving = (clone $query)
            ->where('had_craving', true)
            ->count();

        $moodBreakdown = (clone $query)
            ->selectRaw('mood, COUNT(*) as total')
            ->groupBy('mood')
            ->orderByDesc('total')
            ->get();

        $sleepQualityBreakdown = (clone $query)
            ->whereNotNull('sleep_quality')
            ->selectRaw('sleep_quality, COUNT(*) as total')
            ->groupBy('sleep_quality')
            ->orderByDesc('total')
            ->get();

        $data = [
            'scope' => $isAdmin ? 'all_users' : 'my_logs',
            'total_logs' => $totalLogs,
            'latest_log' => $latestLog,
            'average_stress_level' => $averageStress ? round($averageStress, 2) : 0,
            'average_craving_level' => $averageCraving ? round($averageCraving, 2) : 0,
            'average_energy_level' => $averageEnergy ? round($averageEnergy, 2) : 0,
            'high_craving_days' => $highCravingDays,
            'total_had_craving' => $totalHadCraving,
            'mood_breakdown' => $moodBreakdown,
            'sleep_quality_breakdown' => $sleepQualityBreakdown,
        ];

        return $this->sendResponse($data, 'Mood summary fetched successfully.');
    }
}
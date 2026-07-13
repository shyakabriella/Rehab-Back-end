<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\MoodLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MoodLogController extends BaseController
{
    /**
     * All moods accepted by the application.
     */
    private const ALLOWED_MOODS = [
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
    ];

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

        $roleName = strtolower(trim((string) optional($user->role)->name));

        return $roleName === 'admin';
    }

    /**
     * Convert the received mood value into a clean array.
     *
     * Accepted formats:
     * happy
     * happy,motivated
     * happy|motivated
     * ["happy", "motivated"]
     */
    private function normalizeMoodInput(mixed $value): array
    {
        if (is_array($value)) {
            $moods = $value;
        } elseif (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return [];
            }

            $decodedValue = json_decode($value, true);

            if (
                json_last_error() === JSON_ERROR_NONE &&
                is_array($decodedValue)
            ) {
                $moods = $decodedValue;
            } else {
                $moods = preg_split('/[,|;]+/', $value) ?: [];
            }
        } else {
            return [];
        }

        return collect($moods)
            ->map(function ($mood) {
                return strtolower(trim((string) $mood));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Convert the selected moods into the database string format.
     *
     * Example:
     * ["happy", "motivated"] becomes "happy,motivated"
     */
    private function serializeMoods(array $moods): string
    {
        return implode(',', $moods);
    }

    /**
     * Filter records containing a selected mood.
     *
     * This works when mood is stored as:
     * happy
     * happy,motivated
     * motivated,happy
     */
    private function applyMoodFilter(Builder $query, string $mood): void
    {
        $mood = strtolower(trim($mood));

        if (!in_array($mood, self::ALLOWED_MOODS, true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $moodQuery) use ($mood) {
            $moodQuery
                ->where('mood', $mood)
                ->orWhere('mood', 'like', $mood . ',%')
                ->orWhere('mood', 'like', '%,' . $mood)
                ->orWhere('mood', 'like', '%,' . $mood . ',%');
        });
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
            $query->with('user:id,name,email');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
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
            $this->applyMoodFilter($query, (string) $request->mood);
        }

        if ($request->filled('had_craving')) {
            $query->where(
                'had_craving',
                $request->boolean('had_craving')
            );
        }

        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $moodLogs = $query->paginate($perPage);

        return $this->sendResponse(
            $moodLogs,
            'Mood logs fetched successfully.'
        );
    }

    /**
     * Create daily mood log.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $loggedDate = $request->input(
            'logged_date',
            now()->toDateString()
        );

        $input = array_merge($request->all(), [
            'mood' => $this->normalizeMoodInput(
                $request->input('mood')
            ),
            'logged_date' => $loggedDate,
        ]);

        $validator = Validator::make($input, [
            'mood' => [
                'required',
                'array',
                'min:1',
            ],

            'mood.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(self::ALLOWED_MOODS),
            ],

            'stress_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'craving_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'energy_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'sleep_quality' => [
                'nullable',
                Rule::in([
                    'poor',
                    'fair',
                    'good',
                    'very_good',
                ]),
            ],

            'had_craving' => [
                'nullable',
                'boolean',
            ],

            'main_trigger' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'logged_date' => [
                'required',
                'date',
                Rule::unique('mood_logs', 'logged_date')
                    ->where(function ($query) use ($user) {
                        return $query->where(
                            'user_id',
                            $user->id
                        );
                    }),
            ],
        ], [
            'mood.required' => 'Please select at least one mood.',
            'mood.array' => 'The selected moods are invalid.',
            'mood.min' => 'Please select at least one mood.',
            'mood.*.in' => 'One of the selected moods is invalid.',
            'mood.*.distinct' => 'The same mood cannot be selected twice.',
            'logged_date.unique' => 'You already have a mood log for this date.',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors()
            );
        }

        $validated = $validator->validated();

        $moodLog = MoodLog::create([
            'user_id' => $user->id,

            'mood' => $this->serializeMoods(
                $validated['mood']
            ),

            'stress_level' => $validated['stress_level'] ?? null,

            'craving_level' => $validated['craving_level'] ?? null,

            'energy_level' => $validated['energy_level'] ?? null,

            'sleep_quality' => $validated['sleep_quality'] ?? null,

            'had_craving' => isset($validated['had_craving'])
                ? filter_var(
                    $validated['had_craving'],
                    FILTER_VALIDATE_BOOLEAN
                )
                : false,

            'main_trigger' => $validated['main_trigger'] ?? null,

            'notes' => $validated['notes'] ?? null,

            'logged_date' => $validated['logged_date'],
        ]);

        return $this->sendResponse(
            $moodLog,
            'Mood log created successfully.'
        );
    }

    /**
     * Get one mood log.
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
            return $this->sendError(
                'Mood log not found.',
                [
                    'error' => 'This mood log does not exist or you do not have permission to view it.',
                ]
            );
        }

        return $this->sendResponse(
            $moodLog,
            'Mood log fetched successfully.'
        );
    }

    /**
     * Update mood log.
     *
     * Kept owner-only for privacy.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $moodLog = MoodLog::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError(
                'Mood log not found.',
                [
                    'error' => 'This mood log does not exist or does not belong to this user.',
                ]
            );
        }

        $input = $request->all();

        if ($request->exists('mood')) {
            $input['mood'] = $this->normalizeMoodInput(
                $request->input('mood')
            );
        }

        $validator = Validator::make($input, [
            'mood' => [
                'sometimes',
                'required',
                'array',
                'min:1',
            ],

            'mood.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(self::ALLOWED_MOODS),
            ],

            'stress_level' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'craving_level' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'energy_level' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:10',
            ],

            'sleep_quality' => [
                'sometimes',
                'nullable',
                Rule::in([
                    'poor',
                    'fair',
                    'good',
                    'very_good',
                ]),
            ],

            'had_craving' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'main_trigger' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'logged_date' => [
                'sometimes',
                'nullable',
                'date',
                Rule::unique('mood_logs', 'logged_date')
                    ->where(function ($query) use ($user) {
                        return $query->where(
                            'user_id',
                            $user->id
                        );
                    })
                    ->ignore($moodLog->id),
            ],
        ], [
            'mood.required' => 'Please select at least one mood.',
            'mood.array' => 'The selected moods are invalid.',
            'mood.min' => 'Please select at least one mood.',
            'mood.*.in' => 'One of the selected moods is invalid.',
            'mood.*.distinct' => 'The same mood cannot be selected twice.',
            'logged_date.unique' => 'You already have a mood log for this date.',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors()
            );
        }

        $validated = $validator->validated();

        if (array_key_exists('mood', $validated)) {
            $validated['mood'] = $this->serializeMoods(
                $validated['mood']
            );
        }

        if (array_key_exists('had_craving', $validated)) {
            $validated['had_craving'] = filter_var(
                $validated['had_craving'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        $moodLog->update($validated);

        $moodLog->refresh();

        return $this->sendResponse(
            $moodLog,
            'Mood log updated successfully.'
        );
    }

    /**
     * Delete mood log.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $moodLog = MoodLog::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$moodLog) {
            return $this->sendError(
                'Mood log not found.',
                [
                    'error' => 'This mood log does not exist or does not belong to this user.',
                ]
            );
        }

        $moodLog->delete();

        return $this->sendResponse(
            [],
            'Mood log deleted successfully.'
        );
    }

    /**
     * Get today's mood log.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        if ($isAdmin) {
            $query = MoodLog::query()
                ->with('user:id,name,email')
                ->whereDate(
                    'logged_date',
                    now()->toDateString()
                )
                ->orderByDesc('created_at');

            if ($request->filled('user_id')) {
                $query->where(
                    'user_id',
                    $request->integer('user_id')
                );
            }

            $moodLogs = $query->paginate(
                max(
                    1,
                    min(
                        $request->integer('per_page', 15),
                        100
                    )
                )
            );

            return $this->sendResponse(
                $moodLogs,
                'Today mood logs fetched successfully.'
            );
        }

        $moodLog = MoodLog::query()
            ->where('user_id', $user->id)
            ->whereDate(
                'logged_date',
                now()->toDateString()
            )
            ->first();

        if (!$moodLog) {
            return $this->sendError(
                'No mood log today.',
                [
                    'error' => 'This user has not added a mood log for today.',
                ]
            );
        }

        return $this->sendResponse(
            $moodLog,
            'Today mood log fetched successfully.'
        );
    }

    /**
     * Mood summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->canViewAllMoodLogs($user);

        $query = MoodLog::query();

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->integer('user_id')
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'logged_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'logged_date',
                '<=',
                $request->date_to
            );
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

        /*
         * Count every mood separately.
         *
         * Example:
         * happy,motivated adds:
         * happy = 1
         * motivated = 1
         */
        $moodCounts = [];

        $storedMoods = (clone $query)
            ->whereNotNull('mood')
            ->pluck('mood');

        foreach ($storedMoods as $storedMood) {
            $moods = $this->normalizeMoodInput($storedMood);

            foreach ($moods as $mood) {
                if (!isset($moodCounts[$mood])) {
                    $moodCounts[$mood] = 0;
                }

                $moodCounts[$mood]++;
            }
        }

        arsort($moodCounts);

        $moodBreakdown = collect($moodCounts)
            ->map(function ($total, $mood) {
                return [
                    'mood' => $mood,
                    'total' => $total,
                ];
            })
            ->values();

        $sleepQualityBreakdown = (clone $query)
            ->whereNotNull('sleep_quality')
            ->selectRaw('sleep_quality, COUNT(*) as total')
            ->groupBy('sleep_quality')
            ->orderByDesc('total')
            ->get();

        $data = [
            'scope' => $isAdmin
                ? 'all_users'
                : 'my_logs',

            'total_logs' => $totalLogs,

            'latest_log' => $latestLog,

            'average_stress_level' => $averageStress
                ? round($averageStress, 2)
                : 0,

            'average_craving_level' => $averageCraving
                ? round($averageCraving, 2)
                : 0,

            'average_energy_level' => $averageEnergy
                ? round($averageEnergy, 2)
                : 0,

            'high_craving_days' => $highCravingDays,

            'total_had_craving' => $totalHadCraving,

            'mood_breakdown' => $moodBreakdown,

            'sleep_quality_breakdown' => $sleepQualityBreakdown,
        ];

        return $this->sendResponse(
            $data,
            'Mood summary fetched successfully.'
        );
    }
}
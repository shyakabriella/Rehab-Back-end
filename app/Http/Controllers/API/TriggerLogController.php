<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\TriggerLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TriggerLogController extends BaseController
{
    /**
     * Get all trigger logs for logged-in user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TriggerLog::where('user_id', $request->user()->id)
            ->orderByDesc('triggered_at')
            ->orderByDesc('created_at');

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        if ($request->filled('result')) {
            $query->where('result', $request->result);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('triggered_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('triggered_at', '<=', $request->date_to);
        }

        $triggerLogs = $query->paginate(15);

        return $this->sendResponse($triggerLogs, 'Trigger logs fetched successfully.');
    }

    /**
     * Create trigger log.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trigger_type' => [
                'required',
                Rule::in([
                    'peer_pressure',
                    'stress',
                    'loneliness',
                    'family_conflict',
                    'money_problem',
                    'relationship_issue',
                    'environment',
                    'bad_memory',
                    'boredom',
                    'other',
                ]),
            ],
            'intensity_level' => 'nullable|integer|min:0|max:10',
            'location' => 'nullable|string|max:255',
            'coping_action' => 'nullable|string|max:3000',
            'result' => 'nullable|in:resisted,relapsed,still_struggling',
            'notes' => 'nullable|string|max:3000',
            'triggered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $triggerLog = TriggerLog::create([
            'user_id' => $request->user()->id,
            'trigger_type' => $request->trigger_type,
            'intensity_level' => $request->intensity_level ?? 0,
            'location' => $request->location,
            'coping_action' => $request->coping_action,
            'result' => $request->result ?? 'resisted',
            'notes' => $request->notes,
            'triggered_at' => $request->triggered_at ?? now(),
        ]);

        return $this->sendResponse($triggerLog, 'Trigger log created successfully.');
    }

    /**
     * Get one trigger log.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $triggerLog = TriggerLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$triggerLog) {
            return $this->sendError('Trigger log not found.', [
                'error' => 'This trigger log does not exist or does not belong to this user.'
            ]);
        }

        return $this->sendResponse($triggerLog, 'Trigger log fetched successfully.');
    }

    /**
     * Update trigger log.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $triggerLog = TriggerLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$triggerLog) {
            return $this->sendError('Trigger log not found.', [
                'error' => 'This trigger log does not exist or does not belong to this user.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'trigger_type' => [
                'nullable',
                Rule::in([
                    'peer_pressure',
                    'stress',
                    'loneliness',
                    'family_conflict',
                    'money_problem',
                    'relationship_issue',
                    'environment',
                    'bad_memory',
                    'boredom',
                    'other',
                ]),
            ],
            'intensity_level' => 'nullable|integer|min:0|max:10',
            'location' => 'nullable|string|max:255',
            'coping_action' => 'nullable|string|max:3000',
            'result' => 'nullable|in:resisted,relapsed,still_struggling',
            'notes' => 'nullable|string|max:3000',
            'triggered_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $triggerLog->update($request->only([
            'trigger_type',
            'intensity_level',
            'location',
            'coping_action',
            'result',
            'notes',
            'triggered_at',
        ]));

        return $this->sendResponse($triggerLog, 'Trigger log updated successfully.');
    }

    /**
     * Delete trigger log.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $triggerLog = TriggerLog::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$triggerLog) {
            return $this->sendError('Trigger log not found.', [
                'error' => 'This trigger log does not exist or does not belong to this user.'
            ]);
        }

        $triggerLog->delete();

        return $this->sendResponse([], 'Trigger log deleted successfully.');
    }

    /**
     * Trigger summary for mobile/dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalTriggers = TriggerLog::where('user_id', $userId)->count();

        $latestTrigger = TriggerLog::where('user_id', $userId)
            ->orderByDesc('triggered_at')
            ->orderByDesc('created_at')
            ->first();

        $averageIntensity = TriggerLog::where('user_id', $userId)
            ->avg('intensity_level');

        $highIntensityTriggers = TriggerLog::where('user_id', $userId)
            ->where('intensity_level', '>=', 7)
            ->count();

        $relapseCount = TriggerLog::where('user_id', $userId)
            ->where('result', 'relapsed')
            ->count();

        $mostCommonTrigger = TriggerLog::where('user_id', $userId)
            ->selectRaw('trigger_type, COUNT(*) as total')
            ->groupBy('trigger_type')
            ->orderByDesc('total')
            ->first();

        $data = [
            'total_triggers' => $totalTriggers,
            'latest_trigger' => $latestTrigger,
            'average_intensity_level' => $averageIntensity ? round($averageIntensity, 2) : 0,
            'high_intensity_triggers' => $highIntensityTriggers,
            'relapse_count' => $relapseCount,
            'most_common_trigger' => $mostCommonTrigger ? [
                'trigger_type' => $mostCommonTrigger->trigger_type,
                'total' => $mostCommonTrigger->total,
            ] : null,
        ];

        return $this->sendResponse($data, 'Trigger summary fetched successfully.');
    }
}
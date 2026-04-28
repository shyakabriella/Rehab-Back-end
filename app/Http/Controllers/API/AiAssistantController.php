<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\MoodLog;
use App\Models\TriggerLog;
use App\Services\AiAssistantPromptService;
use App\Services\GeminiSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiAssistantController extends BaseController
{
    public function __construct(
        private GeminiSupportService $geminiSupportService,
        private AiAssistantPromptService $promptService
    ) {
    }

    /**
     * Get all AI assistants/modes available.
     */
    public function assistants(): JsonResponse
    {
        return $this->sendResponse(
            $this->promptService->all(),
            'AI assistants fetched successfully.'
        );
    }

    /**
     * Get all AI sessions for logged-in user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AiSession::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->filled('assistant_type')) {
            $query->where(
                'assistant_type',
                $this->promptService->normalizeAssistantType($request->assistant_type)
            );
        }

        $sessions = $query->paginate(15);

        return $this->sendResponse($sessions, 'AI sessions fetched successfully.');
    }

    /**
     * Start new AI support session.
     */
    public function startSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'assistant_type' => 'nullable|string|max:100',
            'mood_log_id' => 'nullable|integer|exists:mood_logs,id',
            'trigger_log_id' => 'nullable|integer|exists:trigger_logs,id',
            'message' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if ($request->filled('mood_log_id')) {
            $moodLogExists = MoodLog::where('user_id', $user->id)
                ->where('id', $request->mood_log_id)
                ->exists();

            if (!$moodLogExists) {
                return $this->sendError('Invalid mood log.', [
                    'error' => 'This mood log does not belong to this user.',
                ]);
            }
        }

        if ($request->filled('trigger_log_id')) {
            $triggerLogExists = TriggerLog::where('user_id', $user->id)
                ->where('id', $request->trigger_log_id)
                ->exists();

            if (!$triggerLogExists) {
                return $this->sendError('Invalid trigger log.', [
                    'error' => 'This trigger log does not belong to this user.',
                ]);
            }
        }

        $assistantType = $request->filled('assistant_type')
            ? $this->promptService->normalizeAssistantType($request->assistant_type)
            : $this->promptService->detectAssistantTypeFromMessage($request->message ?? '');

        $promptData = $this->promptService->getPromptData($assistantType);

        $session = AiSession::create([
            'user_id' => $user->id,
            'mood_log_id' => $request->mood_log_id,
            'trigger_log_id' => $request->trigger_log_id,
            'title' => $request->title ?: $promptData['assistant_name'],
            'assistant_type' => $promptData['assistant_type'],
            'prompt_file' => $promptData['prompt_file'],
            'prompt_version' => $promptData['prompt_version'],
            'status' => 'active',
            'risk_level' => 'low',
            'started_at' => now(),
        ]);

        AiMessage::create([
            'ai_session_id' => $session->id,
            'sender' => 'system',
            'message' => "{$promptData['assistant_name']} session started. This assistant provides supportive guidance only and does not replace professional medical care.",
            'message_type' => 'text',
            'risk_level' => 'low',
            'recommendation' => null,
            'metadata' => [
                'assistant_type' => $promptData['assistant_type'],
                'assistant_name' => $promptData['assistant_name'],
                'prompt_file' => $promptData['prompt_file'],
                'prompt_version' => $promptData['prompt_version'],
            ],
        ]);

        if ($request->filled('message')) {
            $this->createUserAndAssistantMessages($session, $user, $request->message);
        }

        $session->load(['messages', 'moodLog', 'triggerLog']);

        return $this->sendResponse($session, 'AI session started successfully.');
    }

    /**
     * Show one AI session with messages.
     */
    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = AiSession::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['messages', 'moodLog', 'triggerLog'])
            ->first();

        if (!$session) {
            return $this->sendError('AI session not found.', [
                'error' => 'This AI session does not exist or does not belong to this user.',
            ]);
        }

        return $this->sendResponse($session, 'AI session fetched successfully.');
    }

    /**
     * Send message to AI assistant.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $session = AiSession::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$session) {
            return $this->sendError('AI session not found.', [
                'error' => 'This AI session does not exist or does not belong to this user.',
            ]);
        }

        if ($session->status !== 'active') {
            return $this->sendError('Session closed.', [
                'error' => 'This AI session is not active.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $result = $this->createUserAndAssistantMessages($session, $user, $request->message);

        $session->load(['messages', 'moodLog', 'triggerLog']);

        return $this->sendResponse([
            'session' => $session,
            'assistant_reply' => $result['assistant_message'],
        ], 'AI assistant replied successfully.');
    }

    /**
     * Close AI session.
     */
    public function closeSession(Request $request, int $id): JsonResponse
    {
        $session = AiSession::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$session) {
            return $this->sendError('AI session not found.', [
                'error' => 'This AI session does not exist or does not belong to this user.',
            ]);
        }

        $session->update([
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        return $this->sendResponse($session, 'AI session closed successfully.');
    }

    /**
     * Delete AI session.
     */
    public function destroySession(Request $request, int $id): JsonResponse
    {
        $session = AiSession::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$session) {
            return $this->sendError('AI session not found.', [
                'error' => 'This AI session does not exist or does not belong to this user.',
            ]);
        }

        $session->delete();

        return $this->sendResponse([], 'AI session deleted successfully.');
    }

    /**
     * AI assistant summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalSessions = AiSession::where('user_id', $userId)->count();

        $activeSessions = AiSession::where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        $closedSessions = AiSession::where('user_id', $userId)
            ->where('status', 'closed')
            ->count();

        $highRiskSessions = AiSession::where('user_id', $userId)
            ->whereIn('risk_level', ['high', 'crisis'])
            ->count();

        $totalMessages = AiMessage::whereHas('session', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        $latestSession = AiSession::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        $sessionsByAssistant = AiSession::where('user_id', $userId)
            ->selectRaw('assistant_type, COUNT(*) as total')
            ->groupBy('assistant_type')
            ->orderByDesc('total')
            ->get();

        $data = [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'closed_sessions' => $closedSessions,
            'high_risk_sessions' => $highRiskSessions,
            'total_messages' => $totalMessages,
            'latest_session' => $latestSession,
            'sessions_by_assistant' => $sessionsByAssistant,
            'available_assistants' => $this->promptService->all(),
        ];

        return $this->sendResponse($data, 'AI assistant summary fetched successfully.');
    }

    /**
     * Create user message and assistant reply.
     */
    private function createUserAndAssistantMessages(AiSession $session, $user, string $message): array
    {
        $userMessage = AiMessage::create([
            'ai_session_id' => $session->id,
            'sender' => 'user',
            'message' => $message,
            'message_type' => 'text',
            'risk_level' => null,
            'recommendation' => null,
            'metadata' => [
                'assistant_type' => $session->assistant_type,
                'prompt_file' => $session->prompt_file,
                'prompt_version' => $session->prompt_version,
            ],
        ]);

        $assistantData = $this->geminiSupportService->buildReply(
            $user,
            $message,
            $session->assistant_type
        );

        $riskLevel = $this->normalizeRiskLevel($assistantData['risk_level'] ?? 'low');
        $messageType = $this->normalizeMessageType(
            $assistantData['message_type'] ?? null,
            $riskLevel
        );

        $assistantMessage = AiMessage::create([
            'ai_session_id' => $session->id,
            'sender' => 'assistant',
            'message' => $assistantData['message'] ?? 'I am here to support you.',
            'message_type' => $messageType,
            'risk_level' => $riskLevel,
            'recommendation' => $assistantData['recommendation'] ?? null,
            'metadata' => $assistantData['metadata'] ?? [],
        ]);

        $session->update([
            'risk_level' => $this->highestRisk($session->risk_level ?? 'low', $riskLevel),
        ]);

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    private function normalizeRiskLevel(?string $riskLevel): string
    {
        $riskLevel = strtolower(trim((string) $riskLevel));

        return in_array($riskLevel, ['low', 'medium', 'high', 'crisis'], true)
            ? $riskLevel
            : 'low';
    }

    private function normalizeMessageType(?string $messageType, string $riskLevel): string
    {
        $messageType = strtolower(trim((string) $messageType));

        $aliases = [
            'support' => 'recommendation',
            'supportive' => 'recommendation',
            'advice' => 'recommendation',
            'general' => 'recommendation',
            'info' => 'recommendation',
            'education' => 'recommendation',
            'analysis' => 'recommendation',
            'coaching' => 'recommendation',
            'risk_warning' => 'warning',
            'high_risk' => 'warning',
            'crisis' => 'crisis_support',
            'crisis-support' => 'crisis_support',
            'crisis support' => 'crisis_support',
        ];

        if (isset($aliases[$messageType])) {
            $messageType = $aliases[$messageType];
        }

        $allowed = [
            'text',
            'recommendation',
            'warning',
            'crisis_support',
        ];

        if (in_array($messageType, $allowed, true)) {
            return $messageType;
        }

        if ($riskLevel === 'crisis') {
            return 'crisis_support';
        }

        if ($riskLevel === 'high') {
            return 'warning';
        }

        return 'recommendation';
    }

    private function highestRisk(string $currentRisk, string $newRisk): string
    {
        $levels = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'crisis' => 4,
        ];

        return ($levels[$newRisk] ?? 1) > ($levels[$currentRisk] ?? 1)
            ? $newRisk
            : $currentRisk;
    }
}
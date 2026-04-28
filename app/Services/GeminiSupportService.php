<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\MoodLog;
use App\Models\RecoveryGoal;
use App\Models\Resource;
use App\Models\SobrietyMilestone;
use App\Models\TriggerLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GeminiSupportService
{
    public function __construct(
        private AiAssistantPromptService $promptService
    ) {
    }

    public function buildReply(User $user, string $message, ?string $assistantType = null): array
    {
        $user->loadMissing('profile');

        if (!$assistantType) {
            $assistantType = $this->promptService->detectAssistantTypeFromMessage($message);
        }

        $promptData = $this->promptService->getPromptData($assistantType);

        $latestMood = MoodLog::where('user_id', $user->id)
            ->orderByDesc('logged_date')
            ->first();

        $latestTrigger = TriggerLog::where('user_id', $user->id)
            ->orderByDesc('triggered_at')
            ->orderByDesc('created_at')
            ->first();

        $activeGoal = RecoveryGoal::where('user_id', $user->id)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->first();

        $localRiskLevel = $this->detectRiskLevel($message, $latestMood, $latestTrigger);

        $rehubRelated = $this->isRehubRelatedQuestion($message)
            || $promptData['assistant_type'] !== 'recovery_support';

        $rehubKnowledge = $rehubRelated
            ? $this->buildRehubKnowledge($user, $message)
            : [];

        if ($localRiskLevel === 'crisis') {
            return $this->crisisReply(
                $user,
                $latestMood,
                $latestTrigger,
                $activeGoal,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            );
        }

        $geminiText = $this->callGemini(
            $promptData['prompt_text'],
            $this->buildUserPrompt(
                $user,
                $message,
                $latestMood,
                $latestTrigger,
                $activeGoal,
                $localRiskLevel,
                $rehubRelated,
                $rehubKnowledge,
                $promptData
            )
        );

        if (!$geminiText) {
            return $this->fallbackReply(
                $user,
                $message,
                $latestMood,
                $latestTrigger,
                $activeGoal,
                $localRiskLevel,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            );
        }

        $parsed = $this->parseGeminiJson($geminiText);

        if (!$parsed) {
            $messageType = $this->normalizeMessageType(null, $localRiskLevel);

            return [
                'message' => $geminiText,
                'message_type' => $messageType,
                'risk_level' => $localRiskLevel,
                'recommendation' => 'Keep using safe coping steps and contact a trusted person if the feeling becomes stronger.',
                'metadata' => $this->metadata(
                    $latestMood,
                    $latestTrigger,
                    $activeGoal,
                    true,
                    $promptData,
                    $rehubRelated,
                    $rehubKnowledge
                ),
            ];
        }

        $geminiRiskLevel = $this->normalizeRiskLevel($parsed['risk_level'] ?? $localRiskLevel);
        $finalRiskLevel = $this->highestRisk($localRiskLevel, $geminiRiskLevel);

        if ($finalRiskLevel === 'crisis') {
            return $this->crisisReply(
                $user,
                $latestMood,
                $latestTrigger,
                $activeGoal,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            );
        }

        $messageType = $this->normalizeMessageType(
            $parsed['message_type'] ?? null,
            $finalRiskLevel
        );

        return [
            'message' => $parsed['message_markdown'] ?? $parsed['message'] ?? $geminiText,
            'message_type' => $messageType,
            'risk_level' => $finalRiskLevel,
            'recommendation' => $parsed['recommendation_markdown']
                ?? $parsed['recommendation']
                ?? 'Continue using safe coping steps and contact support if the feeling becomes stronger.',
            'metadata' => $this->metadata(
                $latestMood,
                $latestTrigger,
                $activeGoal,
                true,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            ),
        ];
    }

    private function callGemini(string $systemPrompt, string $userPrompt): ?string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');
        $baseUrl = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $timeout = (int) config('services.gemini.timeout', 12);
        $thinkingBudget = (int) config('services.gemini.thinking_budget', 0);

        if (!$apiKey) {
            Log::warning('Gemini API key is missing. Falling back to local AI support reply.');
            return null;
        }

        $url = "{$baseUrl}/v1beta/models/{$model}:generateContent";

        try {
            $response = Http::timeout($timeout)
                ->retry(1, 100)
                ->post($url . '?key=' . urlencode($apiKey), [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemPrompt],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'topP' => 0.8,
                        'maxOutputTokens' => 600,
                        'responseMimeType' => 'application/json',
                        'thinkingConfig' => [
                            'thinkingBudget' => $thinkingBudget,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();

            return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (Throwable $exception) {
            Log::warning('Gemini API exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildUserPrompt(
        User $user,
        string $message,
        ?MoodLog $latestMood,
        ?TriggerLog $latestTrigger,
        ?RecoveryGoal $activeGoal,
        string $localRiskLevel,
        bool $rehubRelated,
        array $rehubKnowledge,
        array $promptData
    ): string {
        $profile = $user->profile;
        $displayName = $user->display_name ?? $user->name ?? 'Friend';

        $context = [
            'assistant' => [
                'assistant_type' => $promptData['assistant_type'],
                'assistant_name' => $promptData['assistant_name'],
                'prompt_file' => $promptData['prompt_file'],
                'prompt_version' => $promptData['prompt_version'],
            ],
            'user' => [
                'display_name' => $displayName,
                'addiction_type' => $profile?->addiction_type ?? 'recovery',
                'recovery_stage' => $profile?->recovery_stage,
                'support_level' => $profile?->support_level,
                'main_goal' => $profile?->main_goal,
            ],
            'risk' => [
                'local_risk_detection' => $localRiskLevel,
            ],
            'latest_mood' => $latestMood ? [
                'mood' => $latestMood->mood,
                'stress_level' => $latestMood->stress_level,
                'craving_level' => $latestMood->craving_level,
                'energy_level' => $latestMood->energy_level,
                'sleep_quality' => $latestMood->sleep_quality,
                'had_craving' => $latestMood->had_craving,
                'main_trigger' => $latestMood->main_trigger,
                'logged_date' => $latestMood->logged_date,
            ] : null,
            'latest_trigger' => $latestTrigger ? [
                'trigger_type' => $latestTrigger->trigger_type,
                'intensity_level' => $latestTrigger->intensity_level,
                'result' => $latestTrigger->result,
                'location' => $latestTrigger->location,
                'coping_action' => $latestTrigger->coping_action,
                'triggered_at' => $latestTrigger->triggered_at,
            ] : null,
            'active_goal' => $activeGoal ? [
                'title' => $activeGoal->title,
                'category' => $activeGoal->category,
                'priority' => $activeGoal->priority,
                'status' => $activeGoal->status,
                'progress_percent' => $activeGoal->progress_percent,
                'target_date' => $activeGoal->target_date,
            ] : null,
            'rehub_related_question' => $rehubRelated,
            'rehub_knowledge' => $rehubKnowledge,
        ];

        return "Use the following Rehub context JSON when relevant. Do not invent missing data.\n\n"
            . json_encode($context, JSON_PRETTY_PRINT)
            . "\n\nUser message:\n"
            . $message;
    }

    private function parseGeminiJson(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```json\s*/i', '', $clean);
        $clean = preg_replace('/^```\s*/', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function fallbackReply(
        User $user,
        string $message,
        ?MoodLog $latestMood,
        ?TriggerLog $latestTrigger,
        ?RecoveryGoal $activeGoal,
        string $riskLevel,
        array $promptData,
        bool $rehubRelated,
        array $rehubKnowledge
    ): array {
        $displayName = $user->display_name ?? $user->name ?? 'Friend';

        if ($riskLevel === 'high') {
            $reply = "I hear you, **{$displayName}**. This may be a high-risk moment, but you can still protect your recovery.\n\n"
                . "- Move away from the trigger if possible.\n"
                . "- Take slow breaths for 2 minutes.\n"
                . "- Drink water and delay any decision for 20 minutes.\n"
                . "- Contact a trusted person before you act.";

            if ($activeGoal) {
                $reply .= "\n\nRemember your goal: **{$activeGoal->title}**.";
            }

            return [
                'message' => $reply,
                'message_type' => 'warning',
                'risk_level' => 'high',
                'recommendation' => 'Use urgent coping steps and contact a trusted person now.',
                'metadata' => $this->metadata(
                    $latestMood,
                    $latestTrigger,
                    $activeGoal,
                    false,
                    $promptData,
                    $rehubRelated,
                    $rehubKnowledge
                ),
            ];
        }

        $reply = "Thank you for checking in, **{$displayName}**. Recovery is built one safe choice at a time.\n\n"
            . "Try this now:\n\n"
            . "- Name what you are feeling.\n"
            . "- Write down the trigger or thought.\n"
            . "- Choose one healthy action: walk, pray, journal, drink water, or call someone supportive.";

        if ($activeGoal) {
            $reply .= "\n\nA good next step is to continue working on: **{$activeGoal->title}**.";
        }

        return [
            'message' => $reply,
            'message_type' => $this->normalizeMessageType(null, $riskLevel),
            'risk_level' => $riskLevel,
            'recommendation' => 'Continue daily check-ins, mood tracking, and one small positive recovery action.',
            'metadata' => $this->metadata(
                $latestMood,
                $latestTrigger,
                $activeGoal,
                false,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            ),
        ];
    }

    private function crisisReply(
        User $user,
        ?MoodLog $latestMood,
        ?TriggerLog $latestTrigger,
        ?RecoveryGoal $activeGoal,
        array $promptData,
        bool $rehubRelated,
        array $rehubKnowledge
    ): array {
        $displayName = $user->display_name ?? $user->name ?? 'Friend';

        return [
            'message' => "I am really sorry you are feeling this way, **{$displayName}**. Your safety is the most important thing right now.\n\n"
                . "Please do this immediately:\n\n"
                . "- Contact emergency services or a nearby health professional.\n"
                . "- Call or message a trusted person and tell them you need help now.\n"
                . "- Move away from anything you could use to harm yourself or someone else.\n"
                . "- Do not stay alone if you feel unsafe.\n\n"
                . "You do not need to face this alone.",
            'message_type' => 'crisis_support',
            'risk_level' => 'crisis',
            'recommendation' => 'Immediate support recommended: contact emergency services, a trusted person, counselor, family member, or nearby support service now.',
            'metadata' => $this->metadata(
                $latestMood,
                $latestTrigger,
                $activeGoal,
                false,
                $promptData,
                $rehubRelated,
                $rehubKnowledge
            ),
        ];
    }

    private function metadata(
        ?MoodLog $latestMood,
        ?TriggerLog $latestTrigger,
        ?RecoveryGoal $activeGoal,
        bool $usedGemini,
        array $promptData,
        bool $rehubRelated,
        array $rehubKnowledge
    ): array {
        return [
            'provider' => $usedGemini ? 'gemini' : 'local_fallback',
            'model' => $usedGemini ? config('services.gemini.model', 'gemini-2.5-flash-lite') : null,

            'assistant_type' => $promptData['assistant_type'],
            'assistant_name' => $promptData['assistant_name'],
            'prompt_file' => $promptData['prompt_file'],
            'prompt_version' => $promptData['prompt_version'],

            'answer_source' => $rehubRelated
                ? 'gemini_plus_rehub_context'
                : 'gemini_general_answer',

            'rehub_related_question' => $rehubRelated,
            'used_rehub_knowledge' => $rehubRelated && $this->hasRehubKnowledge($rehubKnowledge),

            'knowledge_counts' => [
                'resources' => count($rehubKnowledge['resources'] ?? []),
                'campaigns' => count($rehubKnowledge['campaigns'] ?? []),
                'campaign_contents' => count($rehubKnowledge['campaign_contents'] ?? []),
                'sobriety_milestones' => count($rehubKnowledge['sobriety_milestones'] ?? []),
            ],

            'latest_mood' => $latestMood,
            'latest_trigger' => $latestTrigger,
            'active_goal' => $activeGoal,
        ];
    }

    private function isRehubRelatedQuestion(string $message): bool
    {
        $text = strtolower($message);

        $keywords = [
            'rehab',
            'recovery',
            'recover',
            'addiction',
            'addict',
            'alcohol',
            'drug',
            'drugs',
            'substance',
            'craving',
            'relapse',
            'sobriety',
            'sober',
            'trigger',
            'stress',
            'anxiety',
            'mood',
            'goal',
            'mental health',
            'support',
            'withdrawal',
            'counselor',
            'therapy',
            'family support',
            'check-in',
            'check in',
            'milestone',
            'campaign',
            'resource',
            'habit',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildRehubKnowledge(User $user, string $message): array
    {
        $terms = $this->extractSearchTerms($message);

        $resources = Resource::query()
            ->where('status', 'published')
            ->when(!empty($terms), function ($query) use ($terms) {
                $query->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $query->orWhere('title', 'like', "%{$term}%")
                            ->orWhere('summary', 'like', "%{$term}%")
                            ->orWhere('content', 'like', "%{$term}%")
                            ->orWhere('resource_type', 'like', "%{$term}%");
                    }
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id',
                'title',
                'summary',
                'content',
                'resource_type',
                'external_url',
                'is_featured',
            ]);

        $campaigns = Campaign::query()
            ->where('status', 'published')
            ->when(!empty($terms), function ($query) use ($terms) {
                $query->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $query->orWhere('title', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%")
                            ->orWhere('campaign_type', 'like', "%{$term}%");
                    }
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get([
                'id',
                'title',
                'description',
                'campaign_type',
                'is_featured',
            ]);

        $campaignContents = CampaignContent::query()
            ->where('status', 'published')
            ->when(!empty($terms), function ($query) use ($terms) {
                $query->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $query->orWhere('title', 'like', "%{$term}%")
                            ->orWhere('body', 'like', "%{$term}%")
                            ->orWhere('content_type', 'like', "%{$term}%");
                    }
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get([
                'id',
                'campaign_id',
                'title',
                'body',
                'content_type',
                'is_featured',
            ]);

        $milestones = SobrietyMilestone::query()
            ->where('user_id', $user->id)
            ->orderBy('milestone_days')
            ->limit(7)
            ->get([
                'id',
                'title',
                'milestone_type',
                'milestone_days',
                'status',
                'achieved_date',
            ]);

        return [
            'resources' => $resources->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'summary' => $item->summary,
                'content_preview' => Str::limit((string) $item->content, 700),
                'type' => $item->resource_type,
                'external_url' => $item->external_url,
                'is_featured' => (bool) $item->is_featured,
            ])->values()->toArray(),

            'campaigns' => $campaigns->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description_preview' => Str::limit((string) $item->description, 700),
                'type' => $item->campaign_type,
                'is_featured' => (bool) $item->is_featured,
            ])->values()->toArray(),

            'campaign_contents' => $campaignContents->map(fn ($item) => [
                'id' => $item->id,
                'campaign_id' => $item->campaign_id,
                'title' => $item->title,
                'body_preview' => Str::limit((string) $item->body, 700),
                'type' => $item->content_type,
                'is_featured' => (bool) $item->is_featured,
            ])->values()->toArray(),

            'sobriety_milestones' => $milestones->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'milestone_type' => $item->milestone_type,
                'milestone_days' => $item->milestone_days,
                'status' => $item->status,
                'achieved_date' => $item->achieved_date,
            ])->values()->toArray(),
        ];
    }

    private function extractSearchTerms(string $message): array
    {
        $text = strtolower($message);
        $text = preg_replace('/[^a-z0-9\s]/i', ' ', $text) ?? $text;

        $words = preg_split('/\s+/', $text) ?: [];

        $stopWords = [
            'what',
            'when',
            'where',
            'why',
            'how',
            'can',
            'could',
            'should',
            'would',
            'about',
            'with',
            'from',
            'that',
            'this',
            'have',
            'has',
            'the',
            'and',
            'for',
            'you',
            'your',
            'are',
            'was',
            'were',
            'will',
            'need',
            'want',
            'feel',
            'like',
            'give',
            'help',
            'tell',
            'explain',
        ];

        $terms = [];

        foreach ($words as $word) {
            $word = trim($word);

            if (strlen($word) < 4) {
                continue;
            }

            if (in_array($word, $stopWords, true)) {
                continue;
            }

            $terms[] = $word;
        }

        return array_values(array_unique(array_slice($terms, 0, 8)));
    }

    private function hasRehubKnowledge(array $rehubKnowledge): bool
    {
        return count($rehubKnowledge['resources'] ?? []) > 0
            || count($rehubKnowledge['campaigns'] ?? []) > 0
            || count($rehubKnowledge['campaign_contents'] ?? []) > 0
            || count($rehubKnowledge['sobriety_milestones'] ?? []) > 0;
    }

    private function detectRiskLevel(string $message, ?MoodLog $latestMood, ?TriggerLog $latestTrigger): string
    {
        $text = strtolower($message);

        $crisisWords = [
            'kill myself',
            'suicide',
            'end my life',
            'harm myself',
            'hurt myself',
            'overdose',
            'i want to die',
            'no reason to live',
            'i am not safe',
        ];

        foreach ($crisisWords as $word) {
            if (str_contains($text, $word)) {
                return 'crisis';
            }
        }

        $highWords = [
            'relapse',
            'i want to drink',
            'i want to use drugs',
            'use drugs',
            'drink now',
            'cannot control',
            "can't control",
            'strong craving',
            'withdrawal',
            'tempted',
            'craving is 9',
            'craving is 10',
        ];

        foreach ($highWords as $word) {
            if (str_contains($text, $word)) {
                return 'high';
            }
        }

        if ($latestMood && ((int) $latestMood->craving_level >= 8 || (int) $latestMood->stress_level >= 8)) {
            return 'high';
        }

        if ($latestTrigger && $latestTrigger->result === 'relapsed') {
            return 'high';
        }

        $mediumWords = [
            'stress',
            'stressed',
            'sad',
            'lonely',
            'anxious',
            'angry',
            'trigger',
            'craving',
            'pressure',
            'bored',
            'tired',
        ];

        foreach ($mediumWords as $word) {
            if (str_contains($text, $word)) {
                return 'medium';
            }
        }

        if ($latestMood && ((int) $latestMood->craving_level >= 5 || (int) $latestMood->stress_level >= 5)) {
            return 'medium';
        }

        if ($latestTrigger && (int) $latestTrigger->intensity_level >= 5) {
            return 'medium';
        }

        return 'low';
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
            'recommend' => 'recommendation',
            'recommendations' => 'recommendation',
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
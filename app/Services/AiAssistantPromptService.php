<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AiAssistantPromptService
{
    /**
     * All AI assistant modes available in Rehub.
     * Each mode uses its own .md prompt file.
     */
    private array $assistants = [
        'recovery_support' => [
            'name' => 'Rehub Recovery Support',
            'description' => 'General recovery support for alcohol and drug addiction.',
            'prompt_file' => 'recovery_support.md',
            'version' => 'v1.0',
        ],

        'trigger_support' => [
            'name' => 'Rehub Trigger Support',
            'description' => 'Fast help when the user has cravings, triggers, or relapse risk.',
            'prompt_file' => 'trigger_emergency_support.md',
            'version' => 'v1.0',
        ],

        'goal_coach' => [
            'name' => 'Rehub Goal Coach',
            'description' => 'Helps the user create and follow recovery goals.',
            'prompt_file' => 'goal_coach.md',
            'version' => 'v1.0',
        ],

        'mood_analysis' => [
            'name' => 'Rehub Mood Analyst',
            'description' => 'Helps the user understand mood, stress, cravings, and patterns.',
            'prompt_file' => 'mood_analysis.md',
            'version' => 'v1.0',
        ],

        'awareness_teacher' => [
            'name' => 'Rehub Awareness Teacher',
            'description' => 'Explains recovery, drug prevention, alcohol awareness, and healthy living.',
            'prompt_file' => 'awareness_teacher.md',
            'version' => 'v1.0',
        ],

        'family_support' => [
            'name' => 'Rehub Family Support',
            'description' => 'Guidance for family/friends supporting someone in recovery.',
            'prompt_file' => 'family_support.md',
            'version' => 'v1.0',
        ],

        'relapse_prevention' => [
            'name' => 'Rehub Relapse Prevention',
            'description' => 'Helps users make a relapse prevention and safety plan.',
            'prompt_file' => 'relapse_prevention.md',
            'version' => 'v1.0',
        ],
    ];

    /**
     * Return all assistants for frontend/mobile selection.
     */
    public function all(): array
    {
        return collect($this->assistants)
            ->map(function (array $assistant, string $key) {
                return [
                    'assistant_type' => $key,
                    'name' => $assistant['name'],
                    'description' => $assistant['description'],
                    'prompt_file' => $assistant['prompt_file'],
                    'version' => $assistant['version'],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Normalize assistant type to a supported value.
     */
    public function normalizeAssistantType(?string $assistantType): string
    {
        $assistantType = trim((string) $assistantType);

        if ($assistantType === '') {
            return 'recovery_support';
        }

        $assistantType = strtolower($assistantType);

        $aliases = [
            'recovery' => 'recovery_support',
            'support' => 'recovery_support',
            'general' => 'recovery_support',
            'ai_support' => 'recovery_support',

            'trigger' => 'trigger_support',
            'craving' => 'trigger_support',
            'emergency' => 'trigger_support',
            'urgent' => 'trigger_support',

            'goal' => 'goal_coach',
            'goals' => 'goal_coach',
            'coach' => 'goal_coach',

            'mood' => 'mood_analysis',
            'stress' => 'mood_analysis',
            'anxiety' => 'mood_analysis',

            'awareness' => 'awareness_teacher',
            'education' => 'awareness_teacher',
            'teacher' => 'awareness_teacher',

            'family' => 'family_support',
            'parent' => 'family_support',
            'friend' => 'family_support',

            'relapse' => 'relapse_prevention',
            'prevention' => 'relapse_prevention',
        ];

        if (isset($aliases[$assistantType])) {
            return $aliases[$assistantType];
        }

        return array_key_exists($assistantType, $this->assistants)
            ? $assistantType
            : 'recovery_support';
    }

    /**
     * Detect best assistant type from user's message.
     */
    public function detectAssistantTypeFromMessage(string $message): string
    {
        $text = strtolower($message);

        $triggerWords = [
            'trigger',
            'craving',
            'tempted',
            'want to drink',
            'want to use',
            'relapse now',
            'cannot control',
            "can't control",
            'pressure',
            'withdrawal',
        ];

        foreach ($triggerWords as $word) {
            if (str_contains($text, $word)) {
                return 'trigger_support';
            }
        }

        $relapseWords = [
            'relapse',
            'relapsed',
            'prevention plan',
            'avoid relapse',
            'prevent relapse',
            'safety plan',
        ];

        foreach ($relapseWords as $word) {
            if (str_contains($text, $word)) {
                return 'relapse_prevention';
            }
        }

        $goalWords = [
            'goal',
            'goals',
            'target',
            'progress',
            'plan',
            'routine',
            'habit',
            'milestone',
        ];

        foreach ($goalWords as $word) {
            if (str_contains($text, $word)) {
                return 'goal_coach';
            }
        }

        $moodWords = [
            'mood',
            'stress',
            'stressed',
            'sad',
            'lonely',
            'anxious',
            'angry',
            'depressed',
            'tired',
            'sleep',
        ];

        foreach ($moodWords as $word) {
            if (str_contains($text, $word)) {
                return 'mood_analysis';
            }
        }

        $familyWords = [
            'family',
            'mother',
            'father',
            'parent',
            'wife',
            'husband',
            'friend',
            'support someone',
            'help my brother',
            'help my sister',
        ];

        foreach ($familyWords as $word) {
            if (str_contains($text, $word)) {
                return 'family_support';
            }
        }

        $awarenessWords = [
            'what is',
            'explain',
            'teach',
            'learn',
            'education',
            'awareness',
            'drug prevention',
            'alcohol effects',
            'mental health',
        ];

        foreach ($awarenessWords as $word) {
            if (str_contains($text, $word)) {
                return 'awareness_teacher';
            }
        }

        return 'recovery_support';
    }

    /**
     * Get assistant prompt data.
     */
    public function getPromptData(?string $assistantType = null): array
    {
        $assistantType = $this->normalizeAssistantType($assistantType);

        $assistant = $this->assistants[$assistantType];

        $promptFile = $assistant['prompt_file'];
        $promptPath = resource_path("prompts/{$promptFile}");

        $promptText = null;

        if (is_file($promptPath)) {
            $promptText = file_get_contents($promptPath);
        }

        if (!$promptText) {
            Log::warning('AI prompt file missing or empty. Using fallback prompt.', [
                'assistant_type' => $assistantType,
                'prompt_file' => $promptFile,
                'prompt_path' => $promptPath,
            ]);

            $promptText = $this->fallbackPrompt($assistant['name']);
        }

        return [
            'assistant_type' => $assistantType,
            'assistant_name' => $assistant['name'],
            'description' => $assistant['description'],
            'prompt_file' => $promptFile,
            'prompt_version' => $assistant['version'],
            'prompt_text' => $this->appendRequiredJsonRules($promptText),
        ];
    }

    /**
     * Return only the final prompt text.
     */
    public function getPromptText(?string $assistantType = null): string
    {
        return $this->getPromptData($assistantType)['prompt_text'];
    }

    /**
     * Make sure every prompt always returns safe JSON.
     */
    private function appendRequiredJsonRules(string $promptText): string
    {
        return trim($promptText) . "\n\n" . trim($this->globalOutputRules());
    }

    /**
     * Rules that apply to all assistants.
     */
    private function globalOutputRules(): string
    {
        return <<<'RULES'
---

## Global Rehub AI Rules

You are part of the Rehub mobile application for alcohol and drug addiction recovery support.

Important:
- You are not a doctor.
- You do not replace professional medical care.
- You do not diagnose the user.
- You give supportive, simple, practical recovery guidance.
- Use simple English.
- Be warm, respectful, and non-judgmental.
- If there is danger, overdose, self-harm, suicide, violence, or crisis, tell the user to contact emergency services or a trusted person immediately.
- Do not invent user data.
- If Rehub context is available, use it carefully.
- If Rehub context is missing, answer generally.

## Rehub Knowledge Rule

If `rehub_related_question` is true, use BOTH:
1. Gemini general knowledge.
2. Provided Rehub knowledge and user context.

Rehub knowledge may include:
- user profile
- latest mood log
- latest trigger log
- recovery goals
- sobriety milestones
- resources
- campaigns
- campaign contents

When using Rehub context, say:
- "Based on your recent check-in..."
- "Based on your latest trigger log..."
- "Based on your current recovery goal..."

## Required JSON Output

Return valid JSON only.
Do not wrap JSON in markdown.
Do not add text outside JSON.

Use this exact structure:

{
  "message_markdown": "Your helpful answer in markdown.",
  "risk_level": "low",
  "message_type": "recommendation",
  "recommendation_markdown": "Short practical next step."
}

Allowed risk_level values:
- low
- medium
- high
- crisis

Allowed message_type values:
- text
- recommendation
- warning
- crisis_support

Never use message_type: support.
RULES;
    }

    /**
     * Used when .md file is missing.
     */
    private function fallbackPrompt(string $assistantName): string
    {
        return <<<PROMPT
# {$assistantName}

You are {$assistantName}, a warm recovery-support assistant inside the Rehub mobile app.

Your role:
- Support people recovering from alcohol and drug addiction.
- Help users understand mood, triggers, cravings, goals, and relapse prevention.
- Give simple, safe, practical advice.
- Encourage users to contact trusted people, counselors, health professionals, or emergency services when needed.
PROMPT;
    }
}
<?php

namespace App\Services;

use App\Models\AiSession;
use App\Models\CommunityPost;
use App\Models\MoodLog;
use App\Models\PatientCondition;
use App\Models\RecoveryAward;
use App\Models\RecoveryGoal;
use App\Models\SobrietyMilestone;
use App\Models\SystemActivityLog;
use App\Models\TriggerLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportService
{
    public const REPORT_TYPES = [
        'addiction_patients' => 'Patients by Addiction',
        'full_recovery_awards' => 'Full Recovery Awards',
        'illness' => 'Patient Illness Report',
        'daily_usage' => 'Daily System Usage',
        'recovery_progress' => 'Recovery Progress',
        'relapse_risk' => 'Relapse Risk Review',
    ];

    public function filterOptions(): array
    {
        return [
            'report_types' => collect(self::REPORT_TYPES)
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),

            'patients' => User::query()
                ->whereHas('patientConditions')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),

            'addictions' => PatientCondition::query()
                ->where('condition_type', 'addiction')
                ->select('condition_name')
                ->distinct()
                ->orderBy('condition_name')
                ->pluck('condition_name')
                ->values(),

            'illnesses' => PatientCondition::query()
                ->where('condition_type', 'illness')
                ->select('condition_name')
                ->distinct()
                ->orderBy('condition_name')
                ->pluck('condition_name')
                ->values(),

            'care_statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'in_treatment', 'label' => 'In Treatment'],
                ['value' => 'in_recovery', 'label' => 'In Recovery'],
                ['value' => 'resolved', 'label' => 'Resolved'],
            ],

            'award_statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'eligible', 'label' => 'Eligible for Review'],
                ['value' => 'awarded', 'label' => 'Awarded'],
                ['value' => 'not_eligible', 'label' => 'Not Eligible'],
            ],
        ];
    }

    public function generate(array $filters): array
    {
        return match ($filters['report_type']) {
            'addiction_patients' => $this->addictionPatients($filters),
            'full_recovery_awards' => $this->fullRecoveryAwards($filters),
            'illness' => $this->illnessReport($filters),
            'daily_usage' => $this->dailyUsage($filters),
            'recovery_progress' => $this->recoveryProgress($filters),
            'relapse_risk' => $this->relapseRisk($filters),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    public function awardFullRecovery(User $patient, User $issuer, ?string $notes, bool $force = false): RecoveryAward
    {
        $evaluation = $this->evaluateFullRecovery($patient);

        if (!$evaluation['eligible'] && !$force) {
            throw new \DomainException(
                'This patient does not currently meet the platform full-recovery review criteria.'
            );
        }

        $existingAward = RecoveryAward::query()
            ->where('user_id', $patient->id)
            ->where('award_type', 'full_recovery')
            ->where('status', 'active')
            ->first();

        if ($existingAward) {
            return $existingAward;
        }

        return RecoveryAward::create([
            'user_id' => $patient->id,
            'certificate_number' => $this->certificateNumber(),
            'award_type' => 'full_recovery',
            'title' => 'Full Recovery Recognition Award',
            'awarded_at' => now()->toDateString(),
            'awarded_by' => $issuer->id,
            'status' => 'active',
            'notes' => $notes,
            'criteria_snapshot' => [
                ...$evaluation,
                'manually_overridden' => $force && !$evaluation['eligible'],
            ],
        ])->load(['user:id,name,email', 'issuer:id,name,email']);
    }

    public function revokeAward(RecoveryAward $award, User $revoker, ?string $notes = null): RecoveryAward
    {
        $award->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $revoker->id,
            'notes' => trim(($award->notes ? $award->notes . "\n" : '') . ($notes ?: 'Award revoked.')),
        ]);

        return $award->fresh(['user:id,name,email', 'issuer:id,name,email', 'revoker:id,name,email']);
    }

    public function evaluateFullRecovery(User $patient): array
    {
        $recoveryStartDate = optional($patient->profile)->recovery_start_date;
        $soberDays = $recoveryStartDate
            ? Carbon::parse($recoveryStartDate)->startOfDay()->diffInDays(now()->startOfDay()) + 1
            : 0;

        $moodQuery = MoodLog::query()
            ->where('user_id', $patient->id)
            ->whereDate('logged_date', '>=', now()->subDays(29)->toDateString());

        $moodLogCount = (clone $moodQuery)->count();
        $averageCraving = round((float) ((clone $moodQuery)->avg('craving_level') ?? 0), 2);
        $averageStress = round((float) ((clone $moodQuery)->avg('stress_level') ?? 0), 2);

        $relapsesInLast90Days = TriggerLog::query()
            ->where('user_id', $patient->id)
            ->where('result', 'relapsed')
            ->where('triggered_at', '>=', now()->subDays(90))
            ->count();

        $completedGoals = RecoveryGoal::query()
            ->where('user_id', $patient->id)
            ->where('status', 'completed')
            ->count();

        $oneYearMilestoneAchieved = SobrietyMilestone::query()
            ->where('user_id', $patient->id)
            ->where('status', 'achieved')
            ->where('milestone_days', '>=', 365)
            ->exists();

        $highRiskAiSessions = AiSession::query()
            ->where('user_id', $patient->id)
            ->whereIn('risk_level', ['high', 'crisis'])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $checks = [
            'minimum_365_sober_days' => $soberDays >= 365,
            'minimum_14_mood_logs_last_30_days' => $moodLogCount >= 14,
            'average_craving_at_most_3' => $averageCraving <= 3,
            'average_stress_at_most_5' => $averageStress <= 5,
            'no_relapse_last_90_days' => $relapsesInLast90Days === 0,
            'at_least_one_completed_goal' => $completedGoals >= 1,
            'one_year_milestone_or_equivalent' => $oneYearMilestoneAchieved || $soberDays >= 365,
            'no_high_risk_ai_session_last_30_days' => $highRiskAiSessions === 0,
        ];

        return [
            'eligible' => !in_array(false, $checks, true),
            'checks' => $checks,
            'sober_days' => $soberDays,
            'mood_logs_last_30_days' => $moodLogCount,
            'average_craving_last_30_days' => $averageCraving,
            'average_stress_last_30_days' => $averageStress,
            'relapses_last_90_days' => $relapsesInLast90Days,
            'completed_goals' => $completedGoals,
            'one_year_milestone_achieved' => $oneYearMilestoneAchieved,
            'high_risk_ai_sessions_last_30_days' => $highRiskAiSessions,
            'disclaimer' => 'Platform recognition only. This is not a medical discharge or clinical certification.',
        ];
    }

    private function addictionPatients(array $filters): array
    {
        $users = $this->patientsForCondition('addiction', $filters);
        $snapshots = $this->recoverySnapshots($users, $filters);

        $rows = $users->map(function (User $user) use ($snapshots) {
            $snapshot = $snapshots[$user->id] ?? [];
            $conditions = $user->patientConditions->where('condition_type', 'addiction');

            return [
                'patient_id' => $user->id,
                'patient' => $user->name,
                'email' => $user->email,
                'addiction' => $conditions->pluck('condition_name')->implode(', '),
                'care_status' => $conditions->pluck('care_status')->unique()->map(fn ($v) => Str::headline($v))->implode(', '),
                'recovery_start_date' => optional($user->profile)->recovery_start_date,
                'sober_days' => $snapshot['sober_days'] ?? 0,
                'mood_logs' => $snapshot['mood_logs'] ?? 0,
                'average_craving' => $snapshot['average_craving'] ?? 0,
                'relapses' => $snapshot['relapses'] ?? 0,
                'completed_goals' => $snapshot['completed_goals'] ?? 0,
            ];
        })->values()->all();

        return $this->reportPayload(
            'Patients by Specific Addiction',
            'addiction_patients',
            $filters,
            [
                ['key' => 'patient', 'label' => 'Patient'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'addiction', 'label' => 'Addiction'],
                ['key' => 'care_status', 'label' => 'Care Status'],
                ['key' => 'sober_days', 'label' => 'Sober Days'],
                ['key' => 'average_craving', 'label' => 'Avg. Craving'],
                ['key' => 'relapses', 'label' => 'Relapses'],
                ['key' => 'completed_goals', 'label' => 'Goals Completed'],
            ],
            $rows,
            [
                'patients' => count($rows),
                'in_recovery' => collect($rows)->filter(fn ($row) => str_contains(strtolower($row['care_status']), 'recovery'))->count(),
                'total_relapses' => collect($rows)->sum('relapses'),
                'average_sober_days' => round((float) collect($rows)->avg('sober_days'), 1),
            ],
            ['This report is based on conditions recorded in patient_conditions.']
        );
    }

    private function fullRecoveryAwards(array $filters): array
    {
        $users = $this->patientsForCondition('addiction', $filters);

        $rows = $users->map(function (User $user) {
            $evaluation = $this->evaluateFullRecovery($user);
            $award = RecoveryAward::query()
                ->where('user_id', $user->id)
                ->where('award_type', 'full_recovery')
                ->latest('awarded_at')
                ->first();

            return [
                'patient_id' => $user->id,
                'patient' => $user->name,
                'email' => $user->email,
                'addiction' => $user->patientConditions
                    ->where('condition_type', 'addiction')
                    ->pluck('condition_name')
                    ->implode(', '),
                'sober_days' => $evaluation['sober_days'],
                'mood_logs_30_days' => $evaluation['mood_logs_last_30_days'],
                'average_craving' => $evaluation['average_craving_last_30_days'],
                'relapses_90_days' => $evaluation['relapses_last_90_days'],
                'completed_goals' => $evaluation['completed_goals'],
                'eligibility' => $evaluation['eligible'] ? 'Eligible for review' : 'Not eligible',
                'award_status' => $award?->status ? Str::headline($award->status) : 'Not awarded',
                'certificate_number' => $award?->certificate_number,
                'awarded_at' => optional($award?->awarded_at)->format('Y-m-d'),
            ];
        });

        if (($filters['award_status'] ?? 'all') === 'eligible') {
            $rows = $rows->where('eligibility', 'Eligible for review');
        } elseif (($filters['award_status'] ?? 'all') === 'awarded') {
            $rows = $rows->where('award_status', 'Active');
        } elseif (($filters['award_status'] ?? 'all') === 'not_eligible') {
            $rows = $rows->where('eligibility', 'Not eligible');
        }

        $rows = $rows->values()->all();

        return $this->reportPayload(
            'Full Recovery Award Review',
            'full_recovery_awards',
            $filters,
            [
                ['key' => 'patient', 'label' => 'Patient'],
                ['key' => 'addiction', 'label' => 'Addiction'],
                ['key' => 'sober_days', 'label' => 'Sober Days'],
                ['key' => 'mood_logs_30_days', 'label' => '30-Day Logs'],
                ['key' => 'average_craving', 'label' => 'Avg. Craving'],
                ['key' => 'relapses_90_days', 'label' => '90-Day Relapses'],
                ['key' => 'completed_goals', 'label' => 'Goals'],
                ['key' => 'eligibility', 'label' => 'Review Status'],
                ['key' => 'award_status', 'label' => 'Award Status'],
                ['key' => 'certificate_number', 'label' => 'Certificate'],
            ],
            $rows,
            [
                'patients_reviewed' => count($rows),
                'eligible_for_review' => collect($rows)->where('eligibility', 'Eligible for review')->count(),
                'active_awards' => collect($rows)->where('award_status', 'Active')->count(),
                'not_eligible' => collect($rows)->where('eligibility', 'Not eligible')->count(),
            ],
            [
                'The award must be approved manually by an authorized staff member.',
                'The eligibility calculation is a platform review aid and is not a medical discharge or clinical certification.',
            ]
        );
    }

    private function illnessReport(array $filters): array
    {
        $users = $this->patientsForCondition('illness', $filters);
        $snapshots = $this->recoverySnapshots($users, $filters);

        $rows = collect();

        foreach ($users as $user) {
            $snapshot = $snapshots[$user->id] ?? [];
            $conditions = $user->patientConditions
                ->where('condition_type', 'illness')
                ->when($filters['illness'] ?? null, fn (Collection $items, $illness) => $items->where('condition_name', $illness));

            foreach ($conditions as $condition) {
                $rows->push([
                    'patient_id' => $user->id,
                    'patient' => $user->name,
                    'email' => $user->email,
                    'illness' => $condition->condition_name,
                    'diagnosis_status' => Str::headline($condition->diagnosis_status),
                    'care_status' => Str::headline($condition->care_status),
                    'severity' => $condition->severity ? Str::headline($condition->severity) : 'Not specified',
                    'diagnosed_at' => optional($condition->diagnosed_at)->format('Y-m-d'),
                    'average_stress' => $snapshot['average_stress'] ?? 0,
                    'average_energy' => $snapshot['average_energy'] ?? 0,
                    'health_goals' => $snapshot['health_goals'] ?? 0,
                    'high_risk_ai_sessions' => $snapshot['high_risk_ai_sessions'] ?? 0,
                ]);
            }
        }

        $rows = $rows->values()->all();

        return $this->reportPayload(
            'Patient Illness Report',
            'illness',
            $filters,
            [
                ['key' => 'patient', 'label' => 'Patient'],
                ['key' => 'illness', 'label' => 'Illness'],
                ['key' => 'diagnosis_status', 'label' => 'Diagnosis'],
                ['key' => 'care_status', 'label' => 'Care Status'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'diagnosed_at', 'label' => 'Diagnosed'],
                ['key' => 'average_stress', 'label' => 'Avg. Stress'],
                ['key' => 'average_energy', 'label' => 'Avg. Energy'],
                ['key' => 'health_goals', 'label' => 'Health Goals'],
            ],
            $rows,
            [
                'patient_conditions' => count($rows),
                'active_conditions' => collect($rows)->where('care_status', 'Active')->count(),
                'in_treatment' => collect($rows)->where('care_status', 'In Treatment')->count(),
                'resolved' => collect($rows)->where('care_status', 'Resolved')->count(),
            ],
            ['This is an administrative report and must not replace a clinical diagnosis.']
        );
    }

    private function dailyUsage(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->endOfDay();

        $activity = SystemActivityLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw("DATE(occurred_at) as report_date")
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->selectRaw("SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as logins")
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->get()
            ->keyBy('report_date');

        $moodLogs = MoodLog::query()
            ->whereBetween('logged_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('logged_date as report_date, COUNT(*) as total')
            ->groupBy('logged_date')
            ->pluck('total', 'report_date');

        $triggerLogs = TriggerLog::query()
            ->whereBetween('triggered_at', [$from, $to])
            ->selectRaw('DATE(triggered_at) as report_date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(triggered_at)'))
            ->pluck('total', 'report_date');

        $aiSessions = AiSession::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'report_date');

        $communityPosts = CommunityPost::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'report_date');

        $rows = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($activity, $moodLogs, $triggerLogs, $aiSessions, $communityPosts) {
                $key = $date->toDateString();
                $day = $activity->get($key);

                return [
                    'date' => $key,
                    'active_users' => (int) ($day->active_users ?? 0),
                    'logins' => (int) ($day->logins ?? 0),
                    'api_requests' => (int) ($day->total_requests ?? 0),
                    'mood_logs' => (int) ($moodLogs[$key] ?? 0),
                    'trigger_logs' => (int) ($triggerLogs[$key] ?? 0),
                    'ai_sessions' => (int) ($aiSessions[$key] ?? 0),
                    'community_posts' => (int) ($communityPosts[$key] ?? 0),
                ];
            })
            ->values()
            ->all();

        $moduleBreakdown = SystemActivityLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('module, COUNT(*) as total')
            ->groupBy('module')
            ->orderByDesc('total')
            ->get();

        return $this->reportPayload(
            'Daily System Usage Report',
            'daily_usage',
            $filters,
            [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'active_users', 'label' => 'Active Users'],
                ['key' => 'logins', 'label' => 'Logins'],
                ['key' => 'api_requests', 'label' => 'API Requests'],
                ['key' => 'mood_logs', 'label' => 'Mood Logs'],
                ['key' => 'trigger_logs', 'label' => 'Trigger Logs'],
                ['key' => 'ai_sessions', 'label' => 'AI Sessions'],
                ['key' => 'community_posts', 'label' => 'Community Posts'],
            ],
            $rows,
            [
                'days' => count($rows),
                'unique_active_users' => SystemActivityLog::query()->whereBetween('occurred_at', [$from, $to])->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'total_logins' => collect($rows)->sum('logins'),
                'total_api_requests' => collect($rows)->sum('api_requests'),
            ],
            ['module_breakdown' => $moduleBreakdown]
        );
    }

    private function recoveryProgress(array $filters): array
    {
        $users = $this->patientsForCondition('addiction', $filters);
        $snapshots = $this->recoverySnapshots($users, $filters);

        $rows = $users->map(function (User $user) use ($snapshots) {
            $s = $snapshots[$user->id] ?? [];
            $score = $this->recoveryScore($s);

            return [
                'patient_id' => $user->id,
                'patient' => $user->name,
                'addiction' => $user->patientConditions->where('condition_type', 'addiction')->pluck('condition_name')->implode(', '),
                'sober_days' => $s['sober_days'] ?? 0,
                'mood_logs' => $s['mood_logs'] ?? 0,
                'average_energy' => $s['average_energy'] ?? 0,
                'average_stress' => $s['average_stress'] ?? 0,
                'average_craving' => $s['average_craving'] ?? 0,
                'goal_progress' => $s['average_goal_progress'] ?? 0,
                'achieved_milestones' => $s['achieved_milestones'] ?? 0,
                'recovery_score' => $score,
                'status' => $score >= 75 ? 'Strong progress' : ($score >= 50 ? 'Moderate progress' : 'Needs support'),
            ];
        })->sortByDesc('recovery_score')->values()->all();

        return $this->reportPayload(
            'Patient Recovery Progress Report',
            'recovery_progress',
            $filters,
            [
                ['key' => 'patient', 'label' => 'Patient'],
                ['key' => 'addiction', 'label' => 'Addiction'],
                ['key' => 'sober_days', 'label' => 'Sober Days'],
                ['key' => 'average_energy', 'label' => 'Energy'],
                ['key' => 'average_stress', 'label' => 'Stress'],
                ['key' => 'average_craving', 'label' => 'Craving'],
                ['key' => 'goal_progress', 'label' => 'Goal Progress %'],
                ['key' => 'achieved_milestones', 'label' => 'Milestones'],
                ['key' => 'recovery_score', 'label' => 'Score %'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows,
            [
                'patients' => count($rows),
                'average_recovery_score' => round((float) collect($rows)->avg('recovery_score'), 1),
                'strong_progress' => collect($rows)->where('status', 'Strong progress')->count(),
                'needs_support' => collect($rows)->where('status', 'Needs support')->count(),
            ],
            ['Recovery score is a platform indicator, not a clinical assessment.']
        );
    }

    private function relapseRisk(array $filters): array
    {
        $users = $this->patientsForCondition('addiction', $filters);
        $snapshots = $this->recoverySnapshots($users, $filters);

        $rows = $users->map(function (User $user) use ($snapshots) {
            $s = $snapshots[$user->id] ?? [];
            $riskScore = 0;

            if (($s['relapses'] ?? 0) > 0) {
                $riskScore += 40;
            }

            $riskScore += match (true) {
                ($s['average_craving'] ?? 0) >= 7 => 20,
                ($s['average_craving'] ?? 0) >= 4 => 10,
                default => 0,
            };

            if (($s['high_intensity_triggers'] ?? 0) > 0) {
                $riskScore += 15;
            }

            if (($s['high_risk_ai_sessions'] ?? 0) > 0) {
                $riskScore += 15;
            }

            if (($s['average_stress'] ?? 0) >= 7) {
                $riskScore += 10;
            }

            $riskScore = min(100, $riskScore);

            return [
                'patient_id' => $user->id,
                'patient' => $user->name,
                'addiction' => $user->patientConditions->where('condition_type', 'addiction')->pluck('condition_name')->implode(', '),
                'average_craving' => $s['average_craving'] ?? 0,
                'average_stress' => $s['average_stress'] ?? 0,
                'high_intensity_triggers' => $s['high_intensity_triggers'] ?? 0,
                'relapses' => $s['relapses'] ?? 0,
                'high_risk_ai_sessions' => $s['high_risk_ai_sessions'] ?? 0,
                'risk_score' => $riskScore,
                'risk_level' => match (true) {
                    $riskScore >= 70 => 'Critical',
                    $riskScore >= 50 => 'High',
                    $riskScore >= 25 => 'Moderate',
                    default => 'Low',
                },
            ];
        })->sortByDesc('risk_score')->values()->all();

        return $this->reportPayload(
            'Relapse Risk Review',
            'relapse_risk',
            $filters,
            [
                ['key' => 'patient', 'label' => 'Patient'],
                ['key' => 'addiction', 'label' => 'Addiction'],
                ['key' => 'average_craving', 'label' => 'Avg. Craving'],
                ['key' => 'average_stress', 'label' => 'Avg. Stress'],
                ['key' => 'high_intensity_triggers', 'label' => 'High Triggers'],
                ['key' => 'relapses', 'label' => 'Relapses'],
                ['key' => 'high_risk_ai_sessions', 'label' => 'High-Risk AI'],
                ['key' => 'risk_score', 'label' => 'Risk Score'],
                ['key' => 'risk_level', 'label' => 'Risk Level'],
            ],
            $rows,
            [
                'patients' => count($rows),
                'critical' => collect($rows)->where('risk_level', 'Critical')->count(),
                'high' => collect($rows)->where('risk_level', 'High')->count(),
                'moderate' => collect($rows)->where('risk_level', 'Moderate')->count(),
                'low' => collect($rows)->where('risk_level', 'Low')->count(),
            ],
            ['This is a screening indicator for staff follow-up and not a medical diagnosis.']
        );
    }

    private function patientsForCondition(string $type, array $filters): Collection
    {
        $conditionFilter = $type === 'addiction'
            ? ($filters['addiction'] ?? null)
            : ($filters['illness'] ?? null);

        return User::query()
            ->with([
                'profile',
                'patientConditions' => function ($query) use ($type, $conditionFilter, $filters) {
                    $query->where('condition_type', $type);

                    if ($conditionFilter) {
                        $query->where('condition_name', $conditionFilter);
                    }

                    if ($filters['care_status'] ?? null) {
                        $query->where('care_status', $filters['care_status']);
                    }
                },
            ])
            ->whereHas('patientConditions', function (Builder $query) use ($type, $conditionFilter, $filters) {
                $query->where('condition_type', $type);

                if ($conditionFilter) {
                    $query->where('condition_name', $conditionFilter);
                }

                if ($filters['care_status'] ?? null) {
                    $query->where('care_status', $filters['care_status']);
                }
            })
            ->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->whereKey($userId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function recoverySnapshots(Collection $users, array $filters): array
    {
        $userIds = $users->pluck('id');

        if ($userIds->isEmpty()) {
            return [];
        }

        $moodQuery = MoodLog::query()->whereIn('user_id', $userIds);
        $this->applyDateRange($moodQuery, 'logged_date', $filters);

        $moods = $moodQuery
            ->selectRaw('user_id, COUNT(*) as mood_logs, AVG(stress_level) as average_stress, AVG(craving_level) as average_craving, AVG(energy_level) as average_energy')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $triggerQuery = TriggerLog::query()->whereIn('user_id', $userIds);
        $this->applyDateRange($triggerQuery, 'triggered_at', $filters);

        $triggers = $triggerQuery
            ->selectRaw("user_id, COUNT(*) as trigger_logs, SUM(CASE WHEN result = 'relapsed' THEN 1 ELSE 0 END) as relapses, SUM(CASE WHEN intensity_level >= 7 THEN 1 ELSE 0 END) as high_intensity_triggers")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $goals = RecoveryGoal::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw("user_id, COUNT(*) as total_goals, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals, AVG(progress_percent) as average_goal_progress, SUM(CASE WHEN category IN ('health', 'mental_health') THEN 1 ELSE 0 END) as health_goals")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $milestones = SobrietyMilestone::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw("user_id, SUM(CASE WHEN status = 'achieved' THEN 1 ELSE 0 END) as achieved_milestones, SUM(CASE WHEN status = 'reset' THEN 1 ELSE 0 END) as reset_milestones")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $aiQuery = AiSession::query()->whereIn('user_id', $userIds);
        $this->applyDateRange($aiQuery, 'created_at', $filters);

        $ai = $aiQuery
            ->selectRaw("user_id, COUNT(*) as ai_sessions, SUM(CASE WHEN risk_level IN ('high', 'crisis') THEN 1 ELSE 0 END) as high_risk_ai_sessions")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $snapshots = [];

        foreach ($users as $user) {
            $recoveryStartDate = optional($user->profile)->recovery_start_date;
            $soberDays = $recoveryStartDate
                ? Carbon::parse($recoveryStartDate)->startOfDay()->diffInDays(now()->startOfDay()) + 1
                : 0;

            $mood = $moods->get($user->id);
            $trigger = $triggers->get($user->id);
            $goal = $goals->get($user->id);
            $milestone = $milestones->get($user->id);
            $aiRow = $ai->get($user->id);

            $snapshots[$user->id] = [
                'sober_days' => $soberDays,
                'mood_logs' => (int) ($mood->mood_logs ?? 0),
                'average_stress' => round((float) ($mood->average_stress ?? 0), 2),
                'average_craving' => round((float) ($mood->average_craving ?? 0), 2),
                'average_energy' => round((float) ($mood->average_energy ?? 0), 2),
                'trigger_logs' => (int) ($trigger->trigger_logs ?? 0),
                'relapses' => (int) ($trigger->relapses ?? 0),
                'high_intensity_triggers' => (int) ($trigger->high_intensity_triggers ?? 0),
                'total_goals' => (int) ($goal->total_goals ?? 0),
                'completed_goals' => (int) ($goal->completed_goals ?? 0),
                'average_goal_progress' => round((float) ($goal->average_goal_progress ?? 0), 2),
                'health_goals' => (int) ($goal->health_goals ?? 0),
                'achieved_milestones' => (int) ($milestone->achieved_milestones ?? 0),
                'reset_milestones' => (int) ($milestone->reset_milestones ?? 0),
                'ai_sessions' => (int) ($aiRow->ai_sessions ?? 0),
                'high_risk_ai_sessions' => (int) ($aiRow->high_risk_ai_sessions ?? 0),
            ];
        }

        return $snapshots;
    }

    private function recoveryScore(array $snapshot): int
    {
        $energy = min(10, max(0, (float) ($snapshot['average_energy'] ?? 0)));
        $stress = min(10, max(0, (float) ($snapshot['average_stress'] ?? 0)));
        $craving = min(10, max(0, (float) ($snapshot['average_craving'] ?? 0)));
        $goalProgress = min(100, max(0, (float) ($snapshot['average_goal_progress'] ?? 0)));
        $relapsePenalty = min(30, ((int) ($snapshot['relapses'] ?? 0)) * 15);

        return (int) round(min(100, max(0,
            ($energy * 3) +
            ((10 - $stress) * 2.5) +
            ((10 - $craving) * 2.5) +
            ($goalProgress * 0.2) -
            $relapsePenalty
        )));
    }

    private function applyDateRange(Builder $query, string $column, array $filters): void
    {
        if ($filters['date_from'] ?? null) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if ($filters['date_to'] ?? null) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function reportPayload(
        string $title,
        string $type,
        array $filters,
        array $columns,
        array $rows,
        array $summary,
        array $notes = []
    ): array {
        return [
            'title' => $title,
            'report_type' => $type,
            'generated_at' => now()->toIso8601String(),
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== 'all'),
            'summary' => $summary,
            'columns' => $columns,
            'rows' => $rows,
            'total_rows' => count($rows),
            'notes' => $notes,
        ];
    }

    private function certificateNumber(): string
    {
        do {
            $number = 'RHB-FR-' . now()->format('Y') . '-' . strtoupper(Str::random(8));
        } while (RecoveryAward::where('certificate_number', $number)->exists());

        return $number;
    }
}

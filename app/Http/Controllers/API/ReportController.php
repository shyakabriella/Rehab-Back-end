<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\RecoveryAward;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportController extends BaseController
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function filters(Request $request): JsonResponse
    {
        if (!$this->canManageReports($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can view reporting filters.',
            ], 403);
        }

        return $this->sendResponse(
            $this->reportService->filterOptions(),
            'Report filters fetched successfully.'
        );
    }

    public function generate(Request $request): JsonResponse
    {
        if (!$this->canManageReports($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can generate reports.',
            ], 403);
        }

        $validated = $this->validateFilters($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $report = $this->reportService->generate($validated);

            return $this->sendResponse($report, 'Report generated successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->sendError('Report generation failed.', [
                'error' => app()->isLocal()
                    ? $exception->getMessage()
                    : 'The report could not be generated.',
            ], 500);
        }
    }

    public function exportPdf(Request $request): Response|JsonResponse
    {
        if (!$this->canManageReports($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can export reports.',
            ], 403);
        }

        $validated = $this->validateFilters($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $report = $this->reportService->generate($validated);
            $orientation = $validated['report_type'] === 'daily_usage' ? 'landscape' : 'portrait';
            $filename = sprintf(
                'rhb-%s-%s.pdf',
                Str::slug($report['report_type']),
                now()->format('Ymd-His')
            );

            return Pdf::loadView('reports.professional', [
                'report' => $report,
                'preparedBy' => $request->user(),
            ])
                ->setPaper('a4', $orientation)
                ->download($filename);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->sendError('PDF export failed.', [
                'error' => app()->isLocal()
                    ? $exception->getMessage()
                    : 'The PDF could not be generated.',
            ], 500);
        }
    }

    public function awardFullRecovery(Request $request): JsonResponse
    {
        if (!$this->canManageReports($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can issue recovery awards.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'force' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $patient = User::with(['profile', 'patientConditions'])->findOrFail($request->integer('user_id'));

        try {
            $award = $this->reportService->awardFullRecovery(
                $patient,
                $request->user(),
                $request->input('notes'),
                $request->boolean('force')
            );

            return $this->sendResponse($award, 'Full recovery award issued successfully.');
        } catch (\DomainException $exception) {
            return $this->sendError('Patient is not eligible.', [
                'error' => $exception->getMessage(),
                'evaluation' => $this->reportService->evaluateFullRecovery($patient),
            ], 422);
        }
    }

    public function revokeAward(Request $request, RecoveryAward $award): JsonResponse
    {
        if (!$this->canManageReports($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can revoke recovery awards.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($award->status === 'revoked') {
            return $this->sendError('Award already revoked.', [
                'error' => 'This award was already revoked.',
            ], 422);
        }

        return $this->sendResponse(
            $this->reportService->revokeAward($award, $request->user(), $request->input('notes')),
            'Recovery award revoked successfully.'
        );
    }

    private function validateFilters(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => [
                'required',
                Rule::in(array_keys(ReportService::REPORT_TYPES)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'addiction' => ['nullable', 'string', 'max:150'],
            'illness' => ['nullable', 'string', 'max:150'],
            'care_status' => [
                'nullable',
                Rule::in(['active', 'in_treatment', 'in_recovery', 'resolved']),
            ],
            'award_status' => [
                'nullable',
                Rule::in(['all', 'eligible', 'awarded', 'not_eligible']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $validated = $validator->validated();
        $validated['date_from'] = $validated['date_from'] ?? now()->startOfMonth()->toDateString();
        $validated['date_to'] = $validated['date_to'] ?? now()->toDateString();
        $validated['award_status'] = $validated['award_status'] ?? 'all';

        if (
            $validated['report_type'] === 'daily_usage'
            && Carbon::parse($validated['date_from'])->diffInDays(Carbon::parse($validated['date_to'])) > 366
        ) {
            return $this->sendError('Validation Error.', [
                'date_to' => ['Daily usage reports are limited to a maximum of 366 days.'],
            ], 422);
        }

        return $validated;
    }

    private function canManageReports($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'isModerator') && $user->isModerator()) {
            return true;
        }

        if (method_exists($user, 'canModerateCommunity') && $user->canModerateCommunity()) {
            return true;
        }

        $role = $user->role;
        $roleName = is_object($role)
            ? strtolower(trim((string) ($role->name ?? '')))
            : strtolower(trim((string) $role));

        return in_array($roleName, [
            'admin',
            'administrator',
            'moderator',
            'counselor',
            'doctor',
            'medical staff',
        ], true);
    }
}

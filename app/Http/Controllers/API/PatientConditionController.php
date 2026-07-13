<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\PatientCondition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PatientConditionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canManage($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can view patient conditions.',
            ], 403);
        }

        $query = PatientCondition::query()
            ->with('user:id,name,email')
            ->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('condition_type')) {
            $query->where('condition_type', $request->condition_type);
        }

        if ($request->filled('condition_name')) {
            $query->where('condition_name', $request->condition_name);
        }

        if ($request->filled('care_status')) {
            $query->where('care_status', $request->care_status);
        }

        return $this->sendResponse(
            $query->paginate(max(1, min($request->integer('per_page', 20), 100))),
            'Patient conditions fetched successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->canManage($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can create patient conditions.',
            ], 403);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $condition = PatientCondition::updateOrCreate(
            [
                'user_id' => $request->integer('user_id'),
                'condition_type' => $request->condition_type,
                'condition_name' => trim($request->condition_name),
            ],
            [
                'diagnosis_status' => $request->input('diagnosis_status', 'self_reported'),
                'care_status' => $request->input('care_status', 'active'),
                'severity' => $request->input('severity'),
                'diagnosed_at' => $request->input('diagnosed_at'),
                'resolved_at' => $request->input('resolved_at'),
                'notes' => $request->input('notes'),
            ]
        );

        return $this->sendResponse(
            $condition->load('user:id,name,email'),
            'Patient condition saved successfully.'
        );
    }

    public function update(Request $request, PatientCondition $condition): JsonResponse
    {
        if (!$this->canManage($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can update patient conditions.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'diagnosis_status' => ['sometimes', Rule::in(['self_reported', 'suspected', 'confirmed'])],
            'care_status' => ['sometimes', Rule::in(['active', 'in_treatment', 'in_recovery', 'resolved'])],
            'severity' => ['sometimes', 'nullable', Rule::in(['low', 'moderate', 'high', 'critical'])],
            'diagnosed_at' => ['sometimes', 'nullable', 'date'],
            'resolved_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $condition->update($validator->validated());

        return $this->sendResponse(
            $condition->fresh('user:id,name,email'),
            'Patient condition updated successfully.'
        );
    }

    public function destroy(Request $request, PatientCondition $condition): JsonResponse
    {
        if (!$this->canManage($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only authorized staff can delete patient conditions.',
            ], 403);
        }

        $condition->delete();

        return $this->sendResponse([], 'Patient condition deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'condition_type' => ['required', Rule::in(['addiction', 'illness'])],
            'condition_name' => ['required', 'string', 'max:150'],
            'diagnosis_status' => ['nullable', Rule::in(['self_reported', 'suspected', 'confirmed'])],
            'care_status' => ['nullable', Rule::in(['active', 'in_treatment', 'in_recovery', 'resolved'])],
            'severity' => ['nullable', Rule::in(['low', 'moderate', 'high', 'critical'])],
            'diagnosed_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:diagnosed_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function canManage($user): bool
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

        $role = $user->role;
        $roleName = is_object($role)
            ? strtolower(trim((string) ($role->name ?? '')))
            : strtolower(trim((string) $role));

        return in_array($roleName, ['admin', 'administrator', 'moderator', 'counselor', 'doctor', 'medical staff'], true);
    }
}

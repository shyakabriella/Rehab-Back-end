<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            return $this->sendError('Profile not found.', [
                'error' => 'This user has not created a profile yet.'
            ]);
        }

        return $this->sendResponse($profile, 'Profile fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile) {
            return $this->sendError('Profile already exists.', [
                'error' => 'This user already has a profile. Use update instead.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'age_range' => 'nullable|in:under_18,18_25,26_35,36_45,46_plus',
            'addiction_type' => 'nullable|in:alcohol,drugs,both,other',
            'recovery_stage' => 'nullable|in:beginner,ongoing,stable,relapse_risk',
            'recovery_start_date' => 'nullable|date',
            'main_goal' => 'nullable|string|max:2000',
            'support_level' => 'nullable|in:low,medium,high',
            'privacy_mode' => 'nullable|in:private,anonymous,public',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $profile = Profile::create([
            'user_id' => $user->id,
            'gender' => $request->gender,
            'age_range' => $request->age_range,
            'addiction_type' => $request->addiction_type,
            'recovery_stage' => $request->recovery_stage ?? 'beginner',
            'recovery_start_date' => $request->recovery_start_date,
            'main_goal' => $request->main_goal,
            'support_level' => $request->support_level ?? 'medium',
            'privacy_mode' => $request->privacy_mode ?? 'anonymous',
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'bio' => $request->bio,
        ]);

        return $this->sendResponse($profile, 'Profile created successfully.');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->profile;

        if (!$profile) {
            return $this->sendError('Profile not found.', [
                'error' => 'Create profile first before updating.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'age_range' => 'nullable|in:under_18,18_25,26_35,36_45,46_plus',
            'addiction_type' => 'nullable|in:alcohol,drugs,both,other',
            'recovery_stage' => 'nullable|in:beginner,ongoing,stable,relapse_risk',
            'recovery_start_date' => 'nullable|date',
            'main_goal' => 'nullable|string|max:2000',
            'support_level' => 'nullable|in:low,medium,high',
            'privacy_mode' => 'nullable|in:private,anonymous,public',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $profile->update($request->only([
            'gender',
            'age_range',
            'addiction_type',
            'recovery_stage',
            'recovery_start_date',
            'main_goal',
            'support_level',
            'privacy_mode',
            'emergency_contact_name',
            'emergency_contact_phone',
            'bio',
        ]));

        return $this->sendResponse($profile, 'Profile updated successfully.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            return $this->sendError('Profile not found.', [
                'error' => 'No profile available to delete.'
            ]);
        }

        $profile->delete();

        return $this->sendResponse([], 'Profile deleted successfully.');
    }
}
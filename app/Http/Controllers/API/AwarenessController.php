<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\Resource;
use App\Models\ResourceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AwarenessController extends BaseController
{
    private function canManageAwareness($user): bool
    {
        return $user->isAdmin() || $user->isModerator();
    }

    /*
    |--------------------------------------------------------------------------
    | Resource Categories
    |--------------------------------------------------------------------------
    */

    public function categories(Request $request): JsonResponse
    {
        $query = ResourceCategory::withCount(['resources' => function ($query) {
            $query->where('status', 'published');
        }])->orderBy('name');

        if (!$this->canManageAwareness($request->user())) {
            $query->where('is_active', true);
        }

        $categories = $query->get();

        return $this->sendResponse($categories, 'Resource categories fetched successfully.');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create categories.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:resource_categories,name',
            'description' => 'nullable|string|max:3000',
            'type' => 'nullable|in:alcohol_recovery,drug_prevention,mental_health,family_support,youth_awareness,healthy_lifestyle,general',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $category = ResourceCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(5),
            'description' => $request->description,
            'type' => $request->type ?? 'general',
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->sendResponse($category, 'Resource category created successfully.');
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update categories.'
            ]);
        }

        $category = ResourceCategory::find($id);

        if (!$category) {
            return $this->sendError('Resource category not found.', [
                'error' => 'This category does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255|unique:resource_categories,name,' . $category->id,
            'description' => 'nullable|string|max:3000',
            'type' => 'nullable|in:alcohol_recovery,drug_prevention,mental_health,family_support,youth_awareness,healthy_lifestyle,general',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only(['name', 'description', 'type']);

        if ($request->filled('name')) {
            $data['slug'] = Str::slug($request->name) . '-' . Str::random(5);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $category->update($data);

        return $this->sendResponse($category, 'Resource category updated successfully.');
    }

    public function deleteCategory(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can delete categories.'
            ]);
        }

        $category = ResourceCategory::find($id);

        if (!$category) {
            return $this->sendError('Resource category not found.', [
                'error' => 'This category does not exist.'
            ]);
        }

        $category->delete();

        return $this->sendResponse([], 'Resource category deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Campaigns
    |--------------------------------------------------------------------------
    */

    public function campaigns(Request $request): JsonResponse
    {
        $query = Campaign::withCount('contents')
            ->with('creator:id,name,email')
            ->orderByDesc('created_at');

        if (!$this->canManageAwareness($request->user())) {
            $query->where('status', 'published');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_type')) {
            $query->where('campaign_type', $request->campaign_type);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $campaigns = $query->paginate(15);

        return $this->sendResponse($campaigns, 'Campaigns fetched successfully.');
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create campaigns.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:campaigns,title',
            'description' => 'nullable|string|max:5000',
            'campaign_type' => 'nullable|in:alcohol_awareness,drug_prevention,mental_health,youth_recovery,community_support,general',
            'status' => 'nullable|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|string|max:1000',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $campaign = Campaign::create([
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(5),
            'description' => $request->description,
            'campaign_type' => $request->campaign_type ?? 'general',
            'status' => $request->status ?? 'draft',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'banner_image' => $request->banner_image,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return $this->sendResponse($campaign, 'Campaign created successfully.');
    }

    public function showCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::with([
                'creator:id,name,email',
                'contents' => function ($query) use ($request) {
                    if (!$this->canManageAwareness($request->user())) {
                        $query->where('status', 'published');
                    }

                    $query->orderBy('sort_order')->orderByDesc('created_at');
                }
            ])
            ->find($id);

        if (!$campaign) {
            return $this->sendError('Campaign not found.', [
                'error' => 'This campaign does not exist.'
            ]);
        }

        if ($campaign->status !== 'published' && !$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'You cannot view this campaign.'
            ]);
        }

        return $this->sendResponse($campaign, 'Campaign fetched successfully.');
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update campaigns.'
            ]);
        }

        $campaign = Campaign::find($id);

        if (!$campaign) {
            return $this->sendError('Campaign not found.', [
                'error' => 'This campaign does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255|unique:campaigns,title,' . $campaign->id,
            'description' => 'nullable|string|max:5000',
            'campaign_type' => 'nullable|in:alcohol_awareness,drug_prevention,mental_health,youth_recovery,community_support,general',
            'status' => 'nullable|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|string|max:1000',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'title',
            'description',
            'campaign_type',
            'status',
            'start_date',
            'end_date',
            'banner_image',
        ]);

        if ($request->filled('title')) {
            $data['slug'] = Str::slug($request->title) . '-' . Str::random(5);
        }

        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        $campaign->update($data);

        return $this->sendResponse($campaign, 'Campaign updated successfully.');
    }

    public function deleteCampaign(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can delete campaigns.'
            ]);
        }

        $campaign = Campaign::find($id);

        if (!$campaign) {
            return $this->sendError('Campaign not found.', [
                'error' => 'This campaign does not exist.'
            ]);
        }

        $campaign->delete();

        return $this->sendResponse([], 'Campaign deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign Contents
    |--------------------------------------------------------------------------
    */

    public function storeCampaignContent(Request $request, int $campaignId): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create campaign contents.'
            ]);
        }

        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            return $this->sendError('Campaign not found.', [
                'error' => 'This campaign does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:20000',
            'content_type' => 'nullable|in:message,article,video,image,tip,alert',
            'media_url' => 'nullable|string|max:1000',
            'external_url' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,published,archived',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $content = CampaignContent::create([
            'campaign_id' => $campaign->id,
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'body' => $request->body,
            'content_type' => $request->content_type ?? 'message',
            'media_url' => $request->media_url,
            'external_url' => $request->external_url,
            'status' => $request->status ?? 'draft',
            'sort_order' => $request->sort_order ?? 0,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return $this->sendResponse($content, 'Campaign content created successfully.');
    }

    public function updateCampaignContent(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update campaign contents.'
            ]);
        }

        $content = CampaignContent::find($id);

        if (!$content) {
            return $this->sendError('Campaign content not found.', [
                'error' => 'This campaign content does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:20000',
            'content_type' => 'nullable|in:message,article,video,image,tip,alert',
            'media_url' => 'nullable|string|max:1000',
            'external_url' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,published,archived',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'title',
            'body',
            'content_type',
            'media_url',
            'external_url',
            'status',
            'sort_order',
        ]);

        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        $content->update($data);

        return $this->sendResponse($content, 'Campaign content updated successfully.');
    }

    public function deleteCampaignContent(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can delete campaign contents.'
            ]);
        }

        $content = CampaignContent::find($id);

        if (!$content) {
            return $this->sendError('Campaign content not found.', [
                'error' => 'This campaign content does not exist.'
            ]);
        }

        $content->delete();

        return $this->sendResponse([], 'Campaign content deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    public function resources(Request $request): JsonResponse
    {
        $query = Resource::with([
                'category:id,name,slug,type',
                'creator:id,name,email'
            ])
            ->orderByDesc('created_at');

        if (!$this->canManageAwareness($request->user())) {
            $query->where('status', 'published');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('resource_category_id')) {
            $query->where('resource_category_id', $request->resource_category_id);
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->filled('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('summary', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        $resources = $query->paginate(15);

        return $this->sendResponse($resources, 'Resources fetched successfully.');
    }

    public function storeResource(Request $request): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create resources.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'resource_category_id' => 'nullable|exists:resource_categories,id',
            'title' => 'required|string|max:255|unique:resources,title',
            'summary' => 'nullable|string|max:3000',
            'content' => 'nullable|string|max:30000',
            'resource_type' => 'nullable|in:article,video,audio,guide,tip,external_link',
            'external_url' => 'nullable|string|max:1000',

            // ✅ Upload files instead of typing URL
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm,ogg,mp3,wav,pdf|max:51200',

            'status' => 'nullable|in:draft,published,archived',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $thumbnailPath = null;
        $mediaPath = null;

        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('awareness/thumbnails', 'public');
        }

        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('awareness/media', 'public');
        }

        $resource = Resource::create([
            'resource_category_id' => $request->resource_category_id,
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . Str::random(5),
            'summary' => $request->summary,
            'content' => $request->content,
            'resource_type' => $request->resource_type ?? 'article',
            'media_url' => $mediaPath,
            'external_url' => $request->external_url,
            'thumbnail' => $thumbnailPath,
            'status' => $request->status ?? 'draft',
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return $this->sendResponse($resource, 'Resource created successfully.');
    }

    public function showResource(Request $request, int $id): JsonResponse
    {
        $resource = Resource::with([
                'category:id,name,slug,type',
                'creator:id,name,email'
            ])
            ->find($id);

        if (!$resource) {
            return $this->sendError('Resource not found.', [
                'error' => 'This resource does not exist.'
            ]);
        }

        if ($resource->status !== 'published' && !$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'You cannot view this resource.'
            ]);
        }

        $resource->increment('views_count');

        return $this->sendResponse(
            $resource->fresh(['category:id,name,slug,type', 'creator:id,name,email']),
            'Resource fetched successfully.'
        );
    }

    public function updateResource(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update resources.'
            ]);
        }

        $resource = Resource::find($id);

        if (!$resource) {
            return $this->sendError('Resource not found.', [
                'error' => 'This resource does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'resource_category_id' => 'nullable|exists:resource_categories,id',
            'title' => 'nullable|string|max:255|unique:resources,title,' . $resource->id,
            'summary' => 'nullable|string|max:3000',
            'content' => 'nullable|string|max:30000',
            'resource_type' => 'nullable|in:article,video,audio,guide,tip,external_link',
            'external_url' => 'nullable|string|max:1000',

            // ✅ Upload files instead of typing URL
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm,ogg,mp3,wav,pdf|max:51200',

            'status' => 'nullable|in:draft,published,archived',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'resource_category_id',
            'title',
            'summary',
            'content',
            'resource_type',
            'external_url',
            'status',
        ]);

        if ($request->filled('title')) {
            $data['slug'] = Str::slug($request->title) . '-' . Str::random(5);
        }

        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        if ($request->hasFile('thumbnail')) {
            if ($resource->thumbnail && Storage::disk('public')->exists($resource->thumbnail)) {
                Storage::disk('public')->delete($resource->thumbnail);
            }

            $data['thumbnail'] = $request->file('thumbnail')->store('awareness/thumbnails', 'public');
        }

        if ($request->hasFile('media')) {
            if ($resource->media_url && Storage::disk('public')->exists($resource->media_url)) {
                Storage::disk('public')->delete($resource->media_url);
            }

            $data['media_url'] = $request->file('media')->store('awareness/media', 'public');
        }

        $resource->update($data);

        return $this->sendResponse($resource->fresh(), 'Resource updated successfully.');
    }

    public function deleteResource(Request $request, int $id): JsonResponse
    {
        if (!$this->canManageAwareness($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can delete resources.'
            ]);
        }

        $resource = Resource::find($id);

        if (!$resource) {
            return $this->sendError('Resource not found.', [
                'error' => 'This resource does not exist.'
            ]);
        }

        if ($resource->thumbnail && Storage::disk('public')->exists($resource->thumbnail)) {
            Storage::disk('public')->delete($resource->thumbnail);
        }

        if ($resource->media_url && Storage::disk('public')->exists($resource->media_url)) {
            Storage::disk('public')->delete($resource->media_url);
        }

        $resource->delete();

        return $this->sendResponse([], 'Resource deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Awareness Summary
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request): JsonResponse
    {
        $data = [
            'published_campaigns' => Campaign::where('status', 'published')->count(),
            'featured_campaigns' => Campaign::where('status', 'published')->where('is_featured', true)->count(),
            'published_resources' => Resource::where('status', 'published')->count(),
            'featured_resources' => Resource::where('status', 'published')->where('is_featured', true)->count(),
            'active_categories' => ResourceCategory::where('is_active', true)->count(),
        ];

        if ($this->canManageAwareness($request->user())) {
            $data['draft_campaigns'] = Campaign::where('status', 'draft')->count();
            $data['draft_resources'] = Resource::where('status', 'draft')->count();
        }

        return $this->sendResponse($data, 'Awareness summary fetched successfully.');
    }
}
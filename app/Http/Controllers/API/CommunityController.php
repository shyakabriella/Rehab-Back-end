<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\CommunityComment;
use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Models\ReportedContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommunityController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | Community Groups
    |--------------------------------------------------------------------------
    */

    public function groups(Request $request): JsonResponse
    {
        $query = CommunityGroup::query()
            ->withCount(['posts' => function ($query) {
                $query->where('status', 'published');
            }])
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if (!$request->user()->canModerateCommunity()) {
            $query->where('is_active', true);
        }

        $groups = $query->get();

        return $this->sendResponse($groups, 'Community groups fetched successfully.');
    }

    public function storeGroup(Request $request): JsonResponse
    {
        if (!$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can create community groups.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:community_groups,name',
            'description' => 'nullable|string|max:3000',
            'category' => [
                'nullable',
                Rule::in([
                    'alcohol_recovery',
                    'drug_recovery',
                    'youth_support',
                    'daily_motivation',
                    'relapse_prevention',
                    'mental_health',
                    'general',
                ]),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $group = CommunityGroup::create([
            'created_by' => $request->user()->id,
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(5),
            'description' => $request->description,
            'category' => $request->category ?? 'general',
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->sendResponse($group, 'Community group created successfully.');
    }

    public function showGroup(Request $request, int $id): JsonResponse
    {
        $group = CommunityGroup::withCount(['posts' => function ($query) {
                $query->where('status', 'published');
            }])
            ->find($id);

        if (!$group) {
            return $this->sendError('Community group not found.', [
                'error' => 'This group does not exist.'
            ]);
        }

        if (!$group->is_active && !$request->user()->canModerateCommunity()) {
            return $this->sendError('Community group inactive.', [
                'error' => 'This group is not active.'
            ]);
        }

        return $this->sendResponse($group, 'Community group fetched successfully.');
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update community groups.'
            ]);
        }

        $group = CommunityGroup::find($id);

        if (!$group) {
            return $this->sendError('Community group not found.', [
                'error' => 'This group does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255|unique:community_groups,name,' . $group->id,
            'description' => 'nullable|string|max:3000',
            'category' => [
                'nullable',
                Rule::in([
                    'alcohol_recovery',
                    'drug_recovery',
                    'youth_support',
                    'daily_motivation',
                    'relapse_prevention',
                    'mental_health',
                    'general',
                ]),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'name',
            'description',
            'category',
            'is_active',
        ]);

        if ($request->filled('name')) {
            $data['slug'] = Str::slug($request->name) . '-' . Str::random(5);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $group->update($data);

        return $this->sendResponse($group, 'Community group updated successfully.');
    }

    public function deleteGroup(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can delete community groups.'
            ]);
        }

        $group = CommunityGroup::find($id);

        if (!$group) {
            return $this->sendError('Community group not found.', [
                'error' => 'This group does not exist.'
            ]);
        }

        $group->delete();

        return $this->sendResponse([], 'Community group deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Community Posts
    |--------------------------------------------------------------------------
    */

    public function posts(Request $request): JsonResponse
    {
        $query = CommunityPost::with([
                'group:id,name,slug,category',
                'user:id,name,anonymous_name,is_anonymous'
            ])
            ->withCount(['comments' => function ($query) {
                $query->where('status', 'published');
            }])
            ->orderByDesc('created_at');

        if (!$request->user()->canModerateCommunity()) {
            $query->where('status', 'published');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('community_group_id')) {
            $query->where('community_group_id', $request->community_group_id);
        }

        if ($request->filled('mood_tag')) {
            $query->where('mood_tag', $request->mood_tag);
        }

        if ($request->filled('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('body', 'like', '%' . $request->search . '%');
            });
        }

        $posts = $query->paginate(15);

        return $this->sendResponse($posts, 'Community posts fetched successfully.');
    }

    public function storePost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'community_group_id' => 'required|exists:community_groups,id',
            'title' => 'nullable|string|max:255',
            'body' => 'required|string|max:10000',
            'mood_tag' => [
                'nullable',
                Rule::in([
                    'hopeful',
                    'struggling',
                    'proud',
                    'sad',
                    'stressed',
                    'motivated',
                    'need_support',
                    'general',
                ]),
            ],
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $group = CommunityGroup::where('id', $request->community_group_id)
            ->where('is_active', true)
            ->first();

        if (!$group) {
            return $this->sendError('Community group unavailable.', [
                'error' => 'This community group is not active or does not exist.'
            ]);
        }

        $post = CommunityPost::create([
            'community_group_id' => $request->community_group_id,
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'body' => $request->body,
            'mood_tag' => $request->mood_tag ?? 'general',
            'is_anonymous' => $request->has('is_anonymous') ? $request->boolean('is_anonymous') : true,
            'status' => 'published',
        ]);

        $post->load(['group:id,name,slug,category', 'user:id,name,anonymous_name,is_anonymous']);

        return $this->sendResponse($post, 'Community post created successfully.');
    }

    public function showPost(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::with([
                'group:id,name,slug,category',
                'user:id,name,anonymous_name,is_anonymous',
                'comments' => function ($query) {
                    $query->where('status', 'published')
                        ->with('user:id,name,anonymous_name,is_anonymous')
                        ->orderBy('created_at');
                }
            ])
            ->find($id);

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist.'
            ]);
        }

        if (
            $post->status !== 'published'
            && $post->user_id !== $request->user()->id
            && !$request->user()->canModerateCommunity()
        ) {
            return $this->sendError('Forbidden.', [
                'error' => 'You cannot view this post.'
            ]);
        }

        return $this->sendResponse($post, 'Community post fetched successfully.');
    }

    public function updatePost(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::find($id);

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist.'
            ]);
        }

        if ($post->user_id !== $request->user()->id && !$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'You can only update your own post.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:10000',
            'mood_tag' => [
                'nullable',
                Rule::in([
                    'hopeful',
                    'struggling',
                    'proud',
                    'sad',
                    'stressed',
                    'motivated',
                    'need_support',
                    'general',
                ]),
            ],
            'is_anonymous' => 'nullable|boolean',
            'status' => 'nullable|in:published,hidden,deleted',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'title',
            'body',
            'mood_tag',
            'is_anonymous',
        ]);

        if ($request->has('is_anonymous')) {
            $data['is_anonymous'] = $request->boolean('is_anonymous');
        }

        if ($request->filled('status') && $request->user()->canModerateCommunity()) {
            $data['status'] = $request->status;
        }

        $post->update($data);

        return $this->sendResponse($post, 'Community post updated successfully.');
    }

    public function deletePost(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::find($id);

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist.'
            ]);
        }

        if ($post->user_id !== $request->user()->id && !$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'You can only delete your own post.'
            ]);
        }

        $post->update(['status' => 'deleted']);
        $post->delete();

        return $this->sendResponse([], 'Community post deleted successfully.');
    }

    public function supportPost(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::where('id', $id)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist or is not published.'
            ]);
        }

        $post->increment('support_count');

        return $this->sendResponse($post->fresh(), 'Support added successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Community Comments
    |--------------------------------------------------------------------------
    */

    public function comments(Request $request, int $postId): JsonResponse
    {
        $post = CommunityPost::where('id', $postId)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist or is not published.'
            ]);
        }

        $comments = CommunityComment::where('community_post_id', $post->id)
            ->where('status', 'published')
            ->with('user:id,name,anonymous_name,is_anonymous')
            ->orderBy('created_at')
            ->paginate(20);

        return $this->sendResponse($comments, 'Community comments fetched successfully.');
    }

    public function storeComment(Request $request, int $postId): JsonResponse
    {
        $post = CommunityPost::where('id', $postId)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            return $this->sendError('Community post not found.', [
                'error' => 'This post does not exist or is not published.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:5000',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $comment = CommunityComment::create([
            'community_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'is_anonymous' => $request->has('is_anonymous') ? $request->boolean('is_anonymous') : true,
            'status' => 'published',
        ]);

        $post->increment('comments_count');

        $comment->load('user:id,name,anonymous_name,is_anonymous');

        return $this->sendResponse($comment, 'Community comment created successfully.');
    }

    public function updateComment(Request $request, int $id): JsonResponse
    {
        $comment = CommunityComment::find($id);

        if (!$comment) {
            return $this->sendError('Community comment not found.', [
                'error' => 'This comment does not exist.'
            ]);
        }

        if ($comment->user_id !== $request->user()->id && !$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'You can only update your own comment.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'nullable|string|max:5000',
            'is_anonymous' => 'nullable|boolean',
            'status' => 'nullable|in:published,hidden,deleted',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->only([
            'body',
            'is_anonymous',
        ]);

        if ($request->has('is_anonymous')) {
            $data['is_anonymous'] = $request->boolean('is_anonymous');
        }

        if ($request->filled('status') && $request->user()->canModerateCommunity()) {
            $data['status'] = $request->status;
        }

        $comment->update($data);

        return $this->sendResponse($comment, 'Community comment updated successfully.');
    }

    public function deleteComment(Request $request, int $id): JsonResponse
    {
        $comment = CommunityComment::find($id);

        if (!$comment) {
            return $this->sendError('Community comment not found.', [
                'error' => 'This comment does not exist.'
            ]);
        }

        if ($comment->user_id !== $request->user()->id && !$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'You can only delete your own comment.'
            ]);
        }

        $comment->update(['status' => 'deleted']);

        $post = CommunityPost::find($comment->community_post_id);

        if ($post && $post->comments_count > 0) {
            $post->decrement('comments_count');
        }

        $comment->delete();

        return $this->sendResponse([], 'Community comment deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function reportContent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_type' => 'required|in:post,comment',
            'content_id' => 'required|integer',
            'reason' => 'required|in:abuse,harassment,harmful_advice,spam,triggering_content,privacy_issue,other',
            'details' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $postId = null;
        $commentId = null;

        if ($request->content_type === 'post') {
            $post = CommunityPost::find($request->content_id);

            if (!$post) {
                return $this->sendError('Community post not found.', [
                    'error' => 'This post does not exist.'
                ]);
            }

            $postId = $post->id;
        }

        if ($request->content_type === 'comment') {
            $comment = CommunityComment::find($request->content_id);

            if (!$comment) {
                return $this->sendError('Community comment not found.', [
                    'error' => 'This comment does not exist.'
                ]);
            }

            $commentId = $comment->id;
        }

        $report = ReportedContent::create([
            'reporter_user_id' => $request->user()->id,
            'community_post_id' => $postId,
            'community_comment_id' => $commentId,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending',
        ]);

        return $this->sendResponse($report, 'Content reported successfully.');
    }

    public function reports(Request $request): JsonResponse
    {
        if (!$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can view reports.'
            ]);
        }

        $query = ReportedContent::with([
                'reporter:id,name,email,anonymous_name,is_anonymous',
                'reviewer:id,name,email',
                'post:id,title,body,status',
                'comment:id,body,status'
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(20);

        return $this->sendResponse($reports, 'Reports fetched successfully.');
    }

    public function updateReport(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->canModerateCommunity()) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin or moderator can update reports.'
            ]);
        }

        $report = ReportedContent::find($id);

        if (!$report) {
            return $this->sendError('Report not found.', [
                'error' => 'This report does not exist.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,resolved,dismissed',
            'hide_content' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $report->update([
            'status' => $request->status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($request->boolean('hide_content')) {
            if ($report->community_post_id) {
                CommunityPost::where('id', $report->community_post_id)
                    ->update(['status' => 'hidden']);
            }

            if ($report->community_comment_id) {
                CommunityComment::where('id', $report->community_comment_id)
                    ->update(['status' => 'hidden']);
            }
        }

        return $this->sendResponse($report->fresh(), 'Report updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Community Summary
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'total_active_groups' => CommunityGroup::where('is_active', true)->count(),
            'total_published_posts' => CommunityPost::where('status', 'published')->count(),
            'my_posts' => CommunityPost::where('user_id', $user->id)->count(),
            'my_comments' => CommunityComment::where('user_id', $user->id)->count(),
            'my_reports' => ReportedContent::where('reporter_user_id', $user->id)->count(),
            'pending_reports' => $user->canModerateCommunity()
                ? ReportedContent::where('status', 'pending')->count()
                : null,
        ];

        return $this->sendResponse($data, 'Community summary fetched successfully.');
    }
}
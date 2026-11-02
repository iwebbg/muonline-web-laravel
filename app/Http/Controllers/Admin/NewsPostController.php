<?php

namespace App\Http\Controllers\Admin;

use App\Content\NewsPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNewsPostRequest;
use App\Http\Requests\Admin\UpdateNewsPostRequest;
use App\Models\Administrator;
use App\Models\NewsPost;
use App\Models\NewsPostRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages bilingual news content inside the application database.
 */
final class NewsPostController extends Controller
{
    /**
     * Displays all active articles for permission-scoped content management.
     */
    public function index(Request $request): View
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $posts = NewsPost::query()
            ->withCount('revisions')
            ->latest('updated_at')
            ->get();
        $serializedPosts = [];

        foreach ($posts as $post) {
            $serializedPosts[] = $this->serializeForManager($post);
        }

        return view('admin.news-posts', [
            'posts' => $serializedPosts,
        ]);
    }

    /**
     * Creates one article and its immutable creation revision atomically.
     */
    public function store(StoreNewsPostRequest $request): RedirectResponse
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator): void {
            $attributes = $this->normalizePublication($request->contentData());
            $post = NewsPost::query()->create([
                ...$attributes,
                'created_by_administrator_id' => $administrator->getKey(),
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            $this->recordRevision($post, $administrator, 'created');
        });

        return back()->with('status', __('ui.admin.news_created'));
    }

    /**
     * Updates one article and records the complete resulting state atomically.
     */
    public function update(
        UpdateNewsPostRequest $request,
        NewsPost $newsPost,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator, $newsPost): void {
            $attributes = $this->normalizePublication(
                attributes: $request->contentData(),
                existingPost: $newsPost,
            );

            $newsPost->update([
                ...$attributes,
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            $this->recordRevision($newsPost->refresh(), $administrator, 'updated');
        });

        return back()->with('status', __('ui.admin.news_updated'));
    }

    /**
     * Soft deletes one article while preserving a final recoverable audit snapshot.
     */
    public function destroy(
        Request $request,
        NewsPost $newsPost,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($administrator, $newsPost): void {
            $newsPost->delete();
            $this->recordRevision($newsPost, $administrator, 'deleted');
        });

        return back()->with('status', __('ui.admin.news_deleted'));
    }

    /**
     * Produces the safe article state consumed by the Alpine content manager.
     *
     * @return array<string, mixed>
     */
    private function serializeForManager(NewsPost $post): array
    {
        return [
            'id' => $post->getKey(),
            'slug' => $post->slug,
            'title_en' => $post->title_en,
            'title_bg' => $post->title_bg ?? '',
            'excerpt_en' => $post->excerpt_en,
            'excerpt_bg' => $post->excerpt_bg ?? '',
            'body_en' => $post->body_en,
            'body_bg' => $post->body_bg ?? '',
            'status' => $post->status->value,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i') ?? '',
            'updated_at' => $post->updated_at?->diffForHumans() ?? '',
            'revisions_count' => $post->revisions_count,
            'update_url' => route('admin.news-posts.update', $post),
            'delete_url' => route('admin.news-posts.destroy', $post),
        ];
    }

    /**
     * Enforces consistent publication timestamps for draft and published states.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizePublication(
        array $attributes,
        ?NewsPost $existingPost = null,
    ): array {
        $status = NewsPostStatus::from((string) $attributes['status']);

        if ($status === NewsPostStatus::Draft) {
            $attributes['published_at'] = null;

            return $attributes;
        }

        $suppliedPublishedAt = $attributes['published_at'] ?? null;
        $attributes['published_at'] = $suppliedPublishedAt
            ?: ($existingPost?->published_at ?? now());

        return $attributes;
    }

    /**
     * Stores one immutable content snapshot authored by the administrator.
     */
    private function recordRevision(
        NewsPost $post,
        Administrator $administrator,
        string $action,
    ): void {
        NewsPostRevision::query()->create([
            'news_post_id' => $post->getKey(),
            'administrator_id' => $administrator->getKey(),
            'action' => $action,
            'snapshot' => $post->revisionSnapshot(),
        ]);
    }
}

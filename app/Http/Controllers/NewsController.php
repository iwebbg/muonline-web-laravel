<?php

namespace App\Http\Controllers;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;

/**
 * Displays published bilingual news with English content fallback.
 */
final class NewsController extends Controller
{
    /**
     * Displays the newest published articles without exposing drafts.
     */
    public function index(): View
    {
        $posts = NewsPost::query()
            ->where('type', NewsPostType::News)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return view('news.index', [
            'posts' => $posts,
        ]);
    }

    /**
     * Displays one currently published article resolved by its stable slug.
     */
    public function show(string $slug): View
    {
        $post = NewsPost::query()
            ->where('slug', $slug)
            ->where('type', NewsPostType::News)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return view('news.show', [
            'post' => $post,
        ]);
    }
}

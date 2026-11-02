<?php

namespace App\Http\Controllers;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;

/**
 * Displays published bilingual guides with English content fallback.
 */
final class GuideController extends Controller
{
    /**
     * Displays the published guide library ordered by publication time.
     */
    public function index(): View
    {
        $posts = NewsPost::query()
            ->where('type', NewsPostType::Guide)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return view('guides.index', [
            'posts' => $posts,
        ]);
    }

    /**
     * Displays one currently published guide resolved by its stable slug.
     */
    public function show(string $slug): View
    {
        $post = NewsPost::query()
            ->where('slug', $slug)
            ->where('type', NewsPostType::Guide)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return view('guides.show', [
            'post' => $post,
        ]);
    }
}

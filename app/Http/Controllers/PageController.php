<?php

namespace App\Http\Controllers;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;

/**
 * Displays published bilingual standalone pages with English fallback.
 */
final class PageController extends Controller
{
    /**
     * Displays one currently published standalone page resolved by its stable slug.
     */
    public function __invoke(string $slug): View
    {
        $page = NewsPost::query()
            ->where('slug', $slug)
            ->where('type', NewsPostType::Page)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Content\NewsPostStatus;
use App\Models\Download;
use Illuminate\Contracts\View\View;

/**
 * Displays currently published server downloads without proxying their files.
 */
final class DownloadController extends Controller
{
    /**
     * Displays the ordered download catalog with localized English fallback.
     */
    public function __invoke(): View
    {
        $downloads = Download::query()
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->get();

        return view('downloads.index', [
            'downloads' => $downloads,
        ]);
    }
}

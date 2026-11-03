<?php

namespace App\Http\Controllers\Admin;

use App\Content\NewsPostStatus;
use App\Content\PublicationTime;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDownloadRequest;
use App\Http\Requests\Admin\UpdateDownloadRequest;
use App\Models\Administrator;
use App\Models\Download;
use App\Models\DownloadRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages bilingual HTTPS downloads inside the application database.
 */
final class DownloadController extends Controller
{
    /**
     * Creates the administrator controller with publication timezone conversion.
     */
    public function __construct(
        private readonly PublicationTime $publicationTime,
    ) {}

    /**
     * Displays all active downloads for permission-scoped management.
     */
    public function index(Request $request): View
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $downloads = Download::query()
            ->withCount('revisions')
            ->orderBy('sort_order')
            ->latest('updated_at')
            ->get();
        $serializedDownloads = [];

        foreach ($downloads as $download) {
            $serializedDownloads[] = $this->serializeForManager($download);
        }

        return view('admin.downloads', [
            'downloads' => $serializedDownloads,
        ]);
    }

    /**
     * Creates one download and its immutable creation revision atomically.
     */
    public function store(StoreDownloadRequest $request): RedirectResponse
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator): void {
            $attributes = $this->normalizeAttributes($request->contentData());
            $download = Download::query()->create([
                ...$attributes,
                'created_by_administrator_id' => $administrator->getKey(),
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            $this->recordRevision($download, $administrator, 'created');
        });

        return back()->with('status', __('ui.admin.download_created'));
    }

    /**
     * Updates one download and records the complete resulting state atomically.
     */
    public function update(
        UpdateDownloadRequest $request,
        Download $download,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator, $download): void {
            $attributes = $this->normalizeAttributes(
                attributes: $request->contentData(),
                existingDownload: $download,
            );

            $download->update([
                ...$attributes,
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            $this->recordRevision($download->refresh(), $administrator, 'updated');
        });

        return back()->with('status', __('ui.admin.download_updated'));
    }

    /**
     * Soft deletes one download while preserving a final revision snapshot.
     */
    public function destroy(Request $request, Download $download): RedirectResponse
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($administrator, $download): void {
            $download->delete();
            $this->recordRevision($download, $administrator, 'deleted');
        });

        return back()->with('status', __('ui.admin.download_deleted'));
    }

    /**
     * Produces the safe download state consumed by the Alpine manager.
     *
     * @return array<string, mixed>
     */
    private function serializeForManager(Download $download): array
    {
        return [
            'id' => $download->getKey(),
            'slug' => $download->slug,
            'category' => $download->category->value,
            'title_en' => $download->title_en,
            'title_bg' => $download->title_bg ?? '',
            'description_en' => $download->description_en,
            'description_bg' => $download->description_bg ?? '',
            'version' => $download->version ?? '',
            'file_size' => $download->file_size ?? '',
            'download_url' => $download->download_url,
            'checksum_sha256' => $download->checksum_sha256 ?? '',
            'sort_order' => $download->sort_order,
            'status' => $download->status->value,
            'published_at' => $download->published_at !== null
                ? $this->publicationTime->forAdministratorInput($download->published_at)
                : '',
            'updated_at' => $download->updated_at?->diffForHumans() ?? '',
            'revisions_count' => $download->revisions_count,
            'update_url' => route('admin.downloads.update', $download),
            'delete_url' => route('admin.downloads.destroy', $download),
        ];
    }

    /**
     * Normalizes checksums and publication timestamps before persistence.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(
        array $attributes,
        ?Download $existingDownload = null,
    ): array {
        $status = NewsPostStatus::from((string) $attributes['status']);
        $checksum = $attributes['checksum_sha256'] ?? null;
        $attributes['checksum_sha256'] = is_string($checksum) && $checksum !== ''
            ? strtolower($checksum)
            : null;

        if ($status === NewsPostStatus::Draft) {
            $attributes['published_at'] = null;

            return $attributes;
        }

        $suppliedPublishedAt = $attributes['published_at'] ?? null;

        if (is_string($suppliedPublishedAt) && $suppliedPublishedAt !== '') {
            $attributes['published_at'] = $this->publicationTime
                ->fromAdministratorInput($suppliedPublishedAt);

            return $attributes;
        }

        $attributes['published_at'] = $existingDownload?->published_at ?? now();

        return $attributes;
    }

    /**
     * Stores one immutable download snapshot authored by the administrator.
     */
    private function recordRevision(
        Download $download,
        Administrator $administrator,
        string $action,
    ): void {
        DownloadRevision::query()->create([
            'download_id' => $download->getKey(),
            'administrator_id' => $administrator->getKey(),
            'action' => $action,
            'snapshot' => $download->revisionSnapshot(),
        ]);
    }
}

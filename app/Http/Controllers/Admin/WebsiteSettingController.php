<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WebsiteSettingRevisionConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWebsiteSettingRequest;
use App\Models\Administrator;
use App\Models\WebsiteSettingRevision;
use App\Website\Actions\UpdateWebsiteSettings;
use App\Website\WebsiteSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Manages website-wide branding and search metadata independently of game servers.
 */
final class WebsiteSettingController extends Controller
{
    /**
     * Displays the resolved settings and recent immutable revisions.
     */
    public function edit(WebsiteSettingsManager $settingsManager): View
    {
        $settings = $settingsManager->current();

        return view('admin.website-settings', [
            'settings' => $settings,
            'websiteLogoUrl' => $settings->logoPath === null
                ? null
                : Storage::disk('public')->url($settings->logoPath),
            'websiteOgImageUrl' => $settings->ogImagePath === null
                ? null
                : Storage::disk('public')->url($settings->ogImagePath),
            'recentRevisions' => WebsiteSettingRevision::query()
                ->with('changedByAdministrator')
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /**
     * Publishes text and image settings while cleaning up replaced public assets.
     */
    public function update(
        UpdateWebsiteSettingRequest $request,
        WebsiteSettingsManager $settingsManager,
        UpdateWebsiteSettings $updateWebsiteSettings,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $current = $settingsManager->current();
        $newLogoPath = null;
        $newOgImagePath = null;
        $logoPath = $current->logoPath;
        $ogImagePath = $current->ogImagePath;

        try {
            $newLogoPath = $request->boolean('remove_logo')
                ? null
                : $this->storeUploadedFile($request->file('logo'));
            $newOgImagePath = $request->boolean('remove_og_image')
                ? null
                : $this->storeUploadedFile($request->file('og_image'));
            $logoPath = $request->boolean('remove_logo')
                ? null
                : ($newLogoPath ?: $current->logoPath);
            $ogImagePath = $request->boolean('remove_og_image')
                ? null
                : ($newOgImagePath ?: $current->ogImagePath);

            $updateWebsiteSettings->execute(
                settings: $request->settings($logoPath, $ogImagePath),
                expectedRevision: $request->expectedRevision(),
                administrator: $administrator,
            );
        } catch (WebsiteSettingRevisionConflict) {
            $this->deleteUploadedFiles($newLogoPath, $newOgImagePath);

            return back()->withErrors([
                'settings' => __('ui.admin.website_revision_conflict'),
            ]);
        } catch (Throwable $exception) {
            $this->deleteUploadedFiles($newLogoPath, $newOgImagePath);

            throw $exception;
        }

        $this->deleteReplacedFile($current->logoPath, $logoPath);
        $this->deleteReplacedFile($current->ogImagePath, $ogImagePath);

        return back()->with('status', __('ui.admin.website_updated'));
    }

    /**
     * Removes newly uploaded files when the database revision cannot be saved.
     */
    private function deleteUploadedFiles(?string $logoPath, ?string $ogImagePath): void
    {
        Storage::disk('public')->delete(array_values(array_filter([
            $logoPath,
            $ogImagePath,
        ])));
    }

    /**
     * Removes an old managed asset only after its replacement has been committed.
     */
    private function deleteReplacedFile(?string $previousPath, ?string $currentPath): void
    {
        if ($previousPath !== null && $previousPath !== $currentPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }

    /**
     * Stores one validated public image and reports an unexpected disk failure.
     */
    private function storeUploadedFile(?UploadedFile $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = $file->store('website-branding', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The website asset could not be stored.');
        }

        return $path;
    }
}

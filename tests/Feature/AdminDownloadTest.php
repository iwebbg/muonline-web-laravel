<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use App\Models\Administrator;
use App\Models\Download;
use App\Models\DownloadRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permission-scoped download CRUD, validation and immutable revisions.
 */
final class AdminDownloadTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only administrators with content permission can use the manager.
     */
    public function test_download_manager_requires_content_permission(): void
    {
        $contentManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageContent])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($contentManager, 'admin')
            ->get('/admin/content/downloads')
            ->assertOk()
            ->assertSee('downloadManager(', false);
        $this->actingAs($moderator, 'admin')
            ->get('/admin/content/downloads')
            ->assertForbidden();
    }

    /**
     * Confirms create and update normalize publication data and append revisions.
     */
    public function test_administrator_creates_and_updates_audited_download(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'admin-download-create-update-token';
        $checksum = str_repeat('A', 64);

        $createResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/downloads', $this->downloadPayload([
                '_token' => $csrfToken,
                'checksum_sha256' => $checksum,
                'status' => 'published',
            ]));

        $createResponse->assertRedirect()->assertSessionHasNoErrors();

        $download = Download::query()->sole();

        $this->assertSame(DownloadCategory::Client, $download->category);
        $this->assertSame(NewsPostStatus::Published, $download->status);
        $this->assertSame(strtolower($checksum), $download->checksum_sha256);
        $this->assertNotNull($download->published_at);
        $this->assertSame(1, DownloadRevision::query()->count());

        $updateResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put('/admin/content/downloads/'.$download->getKey(), $this->downloadPayload([
                '_token' => $csrfToken,
                'title_en' => 'Updated full client',
                'status' => 'draft',
            ]));

        $updateResponse->assertRedirect()->assertSessionHasNoErrors();

        $download->refresh();

        $this->assertSame('Updated full client', $download->title_en);
        $this->assertSame(NewsPostStatus::Draft, $download->status);
        $this->assertNull($download->published_at);
        $this->assertSame(2, DownloadRevision::query()->count());
        $this->assertSame(
            'Updated full client',
            DownloadRevision::query()->latest('id')->firstOrFail()->snapshot['title_en'],
        );

        $this->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=download_content')
            ->assertOk()
            ->assertSee('Download content')
            ->assertSee('content:download:'.$download->getKey())
            ->assertSee('Updated full client');
    }

    /**
     * Confirms insecure links and malformed checksums are rejected without writes.
     */
    public function test_download_validation_rejects_insecure_url_and_checksum(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'admin-download-validation-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/downloads', $this->downloadPayload([
                '_token' => $csrfToken,
                'download_url' => 'http://downloads.example.com/client.zip',
                'checksum_sha256' => 'unsafe',
            ]));

        $response->assertSessionHasErrors(['download_url', 'checksum_sha256']);
        $this->assertSame(0, Download::query()->count());
        $this->assertSame(0, DownloadRevision::query()->count());
    }

    /**
     * Confirms deletion remains recoverable and preserves the final snapshot.
     */
    public function test_administrator_soft_deletes_download_with_final_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $download = Download::factory()->published()->create();
        $csrfToken = 'admin-download-delete-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->delete('/admin/content/downloads/'.$download->getKey(), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('downloads', ['id' => $download->getKey()]);

        $revision = DownloadRevision::query()->sole();

        $this->assertSame('deleted', $revision->action);
        $this->assertNotNull($revision->snapshot['deleted_at']);
    }

    /**
     * Returns one complete valid download request payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function downloadPayload(array $overrides = []): array
    {
        return [
            'slug' => 'full-client',
            'category' => 'client',
            'title_en' => 'Full client',
            'title_bg' => 'Пълен клиент',
            'description_en' => 'Complete game client.',
            'description_bg' => 'Пълен клиент за играта.',
            'version' => '97d+99i',
            'file_size' => '850 MB',
            'download_url' => 'https://downloads.example.com/full-client.zip',
            'checksum_sha256' => null,
            'sort_order' => 10,
            'status' => 'draft',
            'published_at' => null,
            ...$overrides,
        ];
    }
}

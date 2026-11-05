<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\Administrator;
use App\Models\NewsPost;
use App\Models\NewsPostRevision;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * Verifies permission-scoped news CRUD and immutable revision history.
 */
final class AdminNewsPostTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only identities with manage-content permission can open the manager.
     */
    public function test_content_manager_requires_granular_permission(): void
    {
        $contentManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageContent])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($contentManager, 'admin')
            ->get('/admin/content/news')
            ->assertOk()
            ->assertSee('contentManager(', false)
            ->assertSee('x-teleport="body"', false);
        $this->actingAs($contentManager, 'admin')
            ->get('/admin')
            ->assertRedirect('/admin/content/news');
        $this->actingAs($moderator, 'admin')
            ->get('/admin/content/news')
            ->assertForbidden();
    }

    /**
     * Confirms content search and type filters run before bounded pagination.
     */
    public function test_content_manager_filters_and_paginates_articles(): void
    {
        $administrator = Administrator::factory()->create();
        NewsPost::factory()->count(21)->create();
        NewsPost::factory()->guide()->create([
            'slug' => 'castle-siege-guide',
            'title_en' => 'Castle Siege preparation',
            'title_bg' => 'Подготовка за Castle Siege',
        ]);

        $filteredResponse = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/content/news?search=Castle+Siege&type=guide');

        $filteredResponse
            ->assertOk()
            ->assertSee('Castle Siege preparation')
            ->assertViewHas('contentFilters', [
                'search' => 'Castle Siege',
                'type' => 'guide',
            ])
            ->assertViewHas(
                'postPaginator',
                function (LengthAwarePaginator $paginator): bool {
                    return $paginator->total() === 1
                        && $paginator->count() === 1;
                },
            );

        $this
            ->get('/admin/content/news')
            ->assertOk()
            ->assertViewHas(
                'postPaginator',
                function (LengthAwarePaginator $paginator): bool {
                    return $paginator->total() === 22
                        && $paginator->count() === 20
                        && $paginator->hasMorePages();
                },
            );
    }

    /**
     * Confirms create and update persist publication rules and complete revisions.
     */
    public function test_administrator_can_create_and_update_audited_article(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'admin-news-create-update-token';
        $createResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/news', $this->articlePayload([
                'status' => 'published',
                '_token' => $csrfToken,
            ]));

        $createResponse
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $post = NewsPost::query()->sole();

        $this->assertSame(NewsPostStatus::Published, $post->status);
        $this->assertSame(NewsPostType::News, $post->type);
        $this->assertNotNull($post->published_at);
        $this->assertSame($administrator->getKey(), $post->created_by_administrator_id);
        $this->assertSame(1, NewsPostRevision::query()->count());
        $this->assertSame('created', NewsPostRevision::query()->sole()->action);

        $updateResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put('/admin/content/news/'.$post->getKey(), $this->articlePayload([
                'title_en' => 'Updated realm opening',
                'status' => 'draft',
                '_token' => $csrfToken,
            ]));

        $updateResponse->assertRedirect();

        $post->refresh();

        $this->assertSame('Updated realm opening', $post->title_en);
        $this->assertSame(NewsPostStatus::Draft, $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame(2, NewsPostRevision::query()->count());
        $this->assertSame('Updated realm opening', NewsPostRevision::query()->latest('id')->firstOrFail()->snapshot['title_en']);
    }

    /**
     * Confirms browser-local publication times become UTC instants visible at the intended moment.
     */
    public function test_local_publication_time_is_converted_to_utc(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-03T10:10:00+00:00'));
        config()->set('game.content_timezone', 'Europe/Sofia');

        $administrator = Administrator::factory()->create();
        $csrfToken = 'admin-news-timezone-token';

        $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/news', $this->articlePayload([
                'status' => 'published',
                'published_at' => '2026-11-03T12:03',
                '_token' => $csrfToken,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $post = NewsPost::query()->sole();

        $this->assertSame('2026-11-03 10:03:00', $post->published_at?->format('Y-m-d H:i:s'));

        $this->get('/news')
            ->assertOk()
            ->assertSee('Realm opening');

        $this->get('/admin/content/news')
            ->assertOk()
            ->assertSee('2026-11-03T12:03');
    }

    /**
     * Confirms deletion is recoverable and preserves a final deleted snapshot.
     */
    public function test_administrator_soft_deletes_article_with_final_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $post = NewsPost::factory()->published()->create();
        $csrfToken = 'admin-news-delete-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->delete('/admin/content/news/'.$post->getKey(), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('news_posts', ['id' => $post->getKey()]);

        $revision = NewsPostRevision::query()->sole();

        $this->assertSame('deleted', $revision->action);
        $this->assertNotNull($revision->snapshot['deleted_at']);
    }

    /**
     * Confirms duplicate or unsafe slugs are rejected before a database write.
     */
    public function test_article_validation_rejects_invalid_slug(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'admin-news-validation-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/news', $this->articlePayload([
                'slug' => '../unsafe-script',
                '_token' => $csrfToken,
            ]));

        $response->assertSessionHasErrors('slug');
        $this->assertSame(0, NewsPost::query()->count());
        $this->assertSame(0, NewsPostRevision::query()->count());
    }

    /**
     * Returns one complete valid bilingual content request payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function articlePayload(array $overrides = []): array
    {
        return [
            'slug' => 'realm-opening',
            'type' => 'news',
            'title_en' => 'Realm opening',
            'title_bg' => 'Откриване на света',
            'excerpt_en' => 'The realm opens this weekend.',
            'excerpt_bg' => 'Светът отваря този уикенд.',
            'body_en' => 'Prepare your guild for the opening.',
            'body_bg' => 'Подгответе гилдията си за откриването.',
            'status' => 'draft',
            'published_at' => null,
            ...$overrides,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Admin\Audit\AuditLogReader;
use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\WebsiteSetting;
use App\Models\WebsiteSettingRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies permission-scoped, audited website branding and search settings.
 */
final class AdminWebsiteSettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests are redirected to the separate administrator login.
     */
    public function test_guest_is_redirected_from_website_settings(): void
    {
        $this->get(route('admin.website-settings.edit'))
            ->assertRedirect(route('admin.login'));
    }

    /**
     * Confirms Game Masters without settings permission cannot read the page.
     */
    public function test_game_master_without_settings_permission_is_forbidden(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();

        $this->actingAs($gameMaster, 'admin')
            ->get(route('admin.website-settings.edit'))
            ->assertForbidden();
    }

    /**
     * Confirms reviewed branding fallbacks are visible before the first revision.
     */
    public function test_settings_administrator_can_view_website_fallbacks(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')
            ->get(route('admin.website-settings.edit'))
            ->assertOk()
            ->assertSee('Website identity and SEO')
            ->assertSee('value="MUONLINE"', false)
            ->assertSee('value="97d + 99i"', false);
    }

    /**
     * Confirms a valid revision controls header, titles, metadata and copyright.
     */
    public function test_valid_update_persists_and_renders_managed_identity(): void
    {
        $administrator = Administrator::factory()->create([
            'name' => 'Website Operator',
            'email' => 'operator@example.com',
        ]);
        $csrfToken = 'website-settings-update-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        $setting = WebsiteSetting::query()->sole();
        $revision = WebsiteSettingRevision::query()->sole();

        $this->assertSame('DREAMMU', $setting->brand_title);
        $this->assertSame('Season 2', $setting->brand_subtitle);
        $this->assertSame('DreamMU', $setting->page_title_suffix);
        $this->assertSame('InterWeb Ltd.', $setting->copyright_holder);
        $this->assertSame(1, $setting->revision);
        $this->assertSame('DREAMMU', $revision->after_snapshot['brand_title']);

        $homeResponse = $this->get('/');

        $homeResponse
            ->assertOk()
            ->assertSee('<title>DreamMU private server</title>', false)
            ->assertSee('<meta name="description" content="Classic MuOnline private server.">', false)
            ->assertSee('<meta name="keywords" content="muonline, dreammu">', false)
            ->assertSee('DREAMMU')
            ->assertSee('Season 2')
            ->assertSee('DREAMMU · Season 2')
            ->assertSee('&copy; '.now()->year.' InterWeb Ltd.', false);

        $rankingsResponse = $this->get('/rankings');

        $rankingsResponse
            ->assertOk()
            ->assertSee('<title>Server rankings - DreamMU</title>', false);

        $historyResponse = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.website-settings.edit'));

        $historyResponse
            ->assertOk()
            ->assertSee('Website Operator')
            ->assertSee('operator@example.com')
            ->assertSee('View')
            ->assertSee('Changed settings')
            ->assertSee('Header title')
            ->assertSee('MUONLINE')
            ->assertSee('DREAMMU')
            ->assertSee('x-teleport="body"', false)
            ->assertSee('aria-modal="true"', false);

        $entries = $this->app->make(AuditLogReader::class)->latest(type: 'website_settings');

        $this->assertCount(1, $entries);
        $this->assertSame('website_settings', $entries[0]['type']);
    }

    /**
     * Confirms a blank copyright override uses the managed inner-page suffix.
     */
    public function test_blank_copyright_holder_uses_page_title_suffix_in_footer(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'website-settings-copyright-fallback-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['copyright_holder'] = '';

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertSessionHas('status');

        $this->assertNull(WebsiteSetting::query()->sole()->copyright_holder);

        $this->get('/')
            ->assertOk()
            ->assertSee('&copy; '.now()->year.' DreamMU.', false);
    }

    /**
     * Confirms validated logo and social images are stored on the public disk.
     */
    public function test_valid_images_are_stored_and_rendered(): void
    {
        Storage::fake('public');
        $administrator = Administrator::factory()->create();
        $csrfToken = 'website-settings-image-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['logo'] = $this->pngUpload('logo.png');
        $payload['og_image'] = $this->pngUpload('share.png');

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        $setting = WebsiteSetting::query()->sole();

        $this->assertNotNull($setting->logo_path);
        $this->assertNotNull($setting->og_image_path);
        Storage::disk('public')->assertExists($setting->logo_path);
        Storage::disk('public')->assertExists($setting->og_image_path);

        $this->get('/')
            ->assertOk()
            ->assertSee('/storage/'.$setting->logo_path, false)
            ->assertSee('<meta property="og:image"', false)
            ->assertSee('/storage/'.$setting->og_image_path, false);
    }

    /**
     * Confirms active image content and extensions are both validated.
     */
    public function test_non_image_logo_is_rejected_without_persisting_settings(): void
    {
        Storage::fake('public');
        $administrator = Administrator::factory()->create();
        $csrfToken = 'website-settings-invalid-image-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['logo'] = UploadedFile::fake()->create('logo.svg', 12, 'image/svg+xml');

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertSessionHasErrors('logo');

        $this->assertSame(0, WebsiteSetting::query()->count());
        $this->assertSame(0, WebsiteSettingRevision::query()->count());
    }

    /**
     * Confirms administrator-provided branding is escaped in the public header.
     */
    public function test_public_branding_escapes_active_markup(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'website-settings-escaping-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['brand_title'] = '<script>alert("brand")</script>';

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertSessionHas('status');

        $this->get('/')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("brand")</script>', false);
    }

    /**
     * Confirms removing managed images clears the snapshot and stored files.
     */
    public function test_image_removal_deletes_replaced_public_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('website-branding/logo.png', 'logo');
        Storage::disk('public')->put('website-branding/share.png', 'share');
        $administrator = Administrator::factory()->create();
        WebsiteSetting::query()->create([
            ...$this->databaseSettings(),
            'logo_path' => 'website-branding/logo.png',
            'og_image_path' => 'website-branding/share.png',
            'revision' => 1,
            'updated_by_administrator_id' => $administrator->getKey(),
        ]);
        $csrfToken = 'website-settings-remove-images-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['revision'] = 1;
        $payload['remove_logo'] = true;
        $payload['remove_og_image'] = true;

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertSessionHas('status');

        $setting = WebsiteSetting::query()->sole();

        $this->assertNull($setting->logo_path);
        $this->assertNull($setting->og_image_path);
        Storage::disk('public')->assertMissing('website-branding/logo.png');
        Storage::disk('public')->assertMissing('website-branding/share.png');
    }

    /**
     * Confirms a stale form cannot overwrite the latest administrator revision.
     */
    public function test_stale_revision_is_rejected_without_another_audit_entry(): void
    {
        $administrator = Administrator::factory()->create();
        WebsiteSetting::query()->create([
            ...$this->databaseSettings(),
            'revision' => 2,
            'updated_by_administrator_id' => $administrator->getKey(),
        ]);
        $csrfToken = 'website-settings-stale-token';
        $payload = $this->settingsPayload();
        $payload['_token'] = $csrfToken;
        $payload['revision'] = 1;

        $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.website-settings.update'), $payload)
            ->assertSessionHasErrors('settings');

        $this->assertSame(2, WebsiteSetting::query()->sole()->revision);
        $this->assertSame(0, WebsiteSettingRevision::query()->count());
    }

    /**
     * Returns one complete valid administrator request payload.
     *
     * @return array<string, mixed>
     */
    private function settingsPayload(): array
    {
        return [
            'revision' => 0,
            'brand_title' => 'DREAMMU',
            'brand_subtitle' => 'Season 2',
            'home_title_en' => 'DreamMU private server',
            'home_title_bg' => 'DreamMU частен сървър',
            'page_title_suffix' => 'DreamMU',
            'meta_description_en' => 'Classic MuOnline private server.',
            'meta_description_bg' => 'Класически MuOnline частен сървър.',
            'meta_keywords_en' => 'muonline, dreammu',
            'meta_keywords_bg' => 'muonline, dreammu, сървър',
            'copyright_holder' => 'InterWeb Ltd.',
        ];
    }

    /**
     * Returns persistence columns for arranging an existing revision.
     *
     * @return array<string, string|null>
     */
    private function databaseSettings(): array
    {
        return [
            'brand_title' => 'DREAMMU',
            'brand_subtitle' => 'Season 2',
            'logo_path' => null,
            'home_title_en' => 'DreamMU private server',
            'home_title_bg' => 'DreamMU частен сървър',
            'page_title_suffix' => 'DreamMU',
            'meta_description_en' => 'Classic MuOnline private server.',
            'meta_description_bg' => 'Класически MuOnline частен сървър.',
            'meta_keywords_en' => 'muonline, dreammu',
            'meta_keywords_bg' => 'muonline, dreammu, сървър',
            'og_image_path' => null,
            'copyright_holder' => 'InterWeb Ltd.',
        ];
    }

    /**
     * Creates a real one-pixel PNG upload without requiring the GD extension.
     */
    private function pngUpload(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZL0sAAAAASUVORK5CYII=',
            true,
        );

        $this->assertIsString($png);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}

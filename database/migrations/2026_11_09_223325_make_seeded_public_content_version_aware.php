<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $currentServer = DB::table('game_servers')
            ->where('is_current', true)
            ->first(['name', 'version']);
        $serverName = is_string($currentServer?->name) && trim($currentServer->name) !== ''
            ? $currentServer->name
            : (string) config('game.profile.name');
        $serverVersion = is_string($currentServer?->version) && trim($currentServer->version) !== ''
            ? $currentServer->version
            : (string) config('game.profile.version');

        $welcomePost = DB::table('news_posts')
            ->where('slug', 'welcome-to-dreammu')
            ->first();

        if ($welcomePost !== null) {
            $welcomeUpdates = [];

            if ($welcomePost->title_en === 'Welcome to DreamMu 97d+99i') {
                $welcomeUpdates['title_en'] = 'Welcome to '.$serverName;
            }

            if ($welcomePost->title_bg === 'Добре дошли в DreamMu 97d+99i') {
                $welcomeUpdates['title_bg'] = 'Добре дошли в '.$serverName;
            }

            $englishOpening = 'DreamMu opens the gates to a classic 97d+99i experience.';
            $bulgarianOpening = 'DreamMu отваря вратите към класическо 97d+99i изживяване.';

            if (str_starts_with($welcomePost->body_en, $englishOpening)) {
                $welcomeUpdates['body_en'] = str_replace(
                    $englishOpening,
                    "{$serverName} opens the gates to a classic {$serverVersion} experience.",
                    $welcomePost->body_en,
                );
            }

            if (str_starts_with($welcomePost->body_bg, $bulgarianOpening)) {
                $welcomeUpdates['body_bg'] = str_replace(
                    $bulgarianOpening,
                    "{$serverName} отваря вратите към класическо {$serverVersion} изживяване.",
                    $welcomePost->body_bg,
                );
            }

            if ($welcomeUpdates !== []) {
                DB::table('news_posts')->where('id', $welcomePost->id)->update($welcomeUpdates);
            }
        }

        DB::table('news_posts')
            ->where('slug', 'getting-started')
            ->where('title_en', 'Getting started on DreamMu')
            ->update([
                'title_en' => 'Getting started on '.$serverName,
                'title_bg' => 'Първи стъпки в '.$serverName,
            ]);

        $downloads = DB::table('downloads')
            ->whereIn('slug', [
                'full-client-mirror-1',
                'full-client-mirror-2',
                'full-client-mirror-3',
            ])
            ->get();

        foreach ($downloads as $download) {
            $downloadUpdates = [];
            $englishClient = 'Complete DreamMu 97d+99i client';
            $bulgarianClient = 'Пълен DreamMu 97d+99i клиент';

            if (str_starts_with($download->description_en, $englishClient)) {
                $downloadUpdates['description_en'] = str_replace(
                    $englishClient,
                    "Complete {$serverName} client",
                    $download->description_en,
                );
            }

            if (str_starts_with($download->description_bg, $bulgarianClient)) {
                $downloadUpdates['description_bg'] = str_replace(
                    $bulgarianClient,
                    "Пълен {$serverName} клиент",
                    $download->description_bg,
                );
            }

            if ($download->version === '97d+99i') {
                $downloadUpdates['version'] = $serverVersion;
            }

            if ($downloadUpdates !== []) {
                DB::table('downloads')->where('id', $download->id)->update($downloadUpdates);
            }
        }
    }

    /**
     * Keeps potentially edited public content unchanged during rollback.
     */
    public function down(): void {}
};

<?php

namespace Database\Seeders;

use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\Download;
use App\Models\NewsPost;
use App\Servers\CurrentGameServer;
use DateTimeInterface;
use Illuminate\Database\Seeder;

/**
 * Seeds editable demonstration content for every public content collection.
 */
final class DemoContentSeeder extends Seeder
{
    /**
     * Creates the seeder with the administrator-managed current server source.
     */
    public function __construct(
        private readonly CurrentGameServer $currentGameServer,
    ) {}

    /**
     * Creates missing demo entries without restoring or overwriting hidden content.
     */
    public function run(): void
    {
        $publishedAt = now();
        $serverName = $this->currentGameServer->name();
        $serverVersion = $this->currentGameServer->version();

        foreach ($this->news($publishedAt, $serverName, $serverVersion) as $post) {
            NewsPost::query()->firstOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }

        foreach ($this->guides($publishedAt, $serverName) as $post) {
            NewsPost::query()->firstOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }

        foreach ($this->downloads($publishedAt, $serverName, $serverVersion) as $download) {
            Download::query()->firstOrCreate(
                ['slug' => $download['slug']],
                $download,
            );
        }
    }

    /**
     * Returns published bilingual server announcements suitable for replacement.
     *
     * @return list<array<string, mixed>>
     */
    private function news(
        DateTimeInterface $publishedAt,
        string $serverName,
        string $serverVersion,
    ): array {
        return [
            [
                'slug' => 'welcome-to-dreammu',
                'type' => NewsPostType::News,
                'title_en' => 'Welcome to '.$serverName,
                'title_bg' => 'Добре дошли в '.$serverName,
                'excerpt_en' => 'A classic MuOnline adventure with familiar progression, active events and account tools in one place.',
                'excerpt_bg' => 'Класическо MuOnline приключение с познат прогрес, активни евенти и управление на акаунта на едно място.',
                'body_en' => "{$serverName} opens the gates to a classic {$serverVersion} experience. Create your account, choose your first character and follow the event schedule from the website.\n\nThe server settings, rates and event times shown on the website are connected to the current server configuration. Keep an eye on the news section for maintenance notices, competitions and future updates.\n\nGather your party, build your guild and begin your journey through Lorencia, Noria and Devias.",
                'body_bg' => "{$serverName} отваря вратите към класическо {$serverVersion} изживяване. Създайте акаунт, изберете първия си герой и следете графика на евентите от уебсайта.\n\nНастройките, rates и часовете на евентите в сайта са свързани с текущата конфигурация на сървъра. Следете новините за профилактики, състезания и бъдещи обновявания.\n\nСъберете party, изградете гилдия и започнете приключението си в Lorencia, Noria и Devias.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'weekend-blood-castle-race',
                'type' => NewsPostType::News,
                'title_en' => 'Weekend Blood Castle race',
                'title_bg' => 'Уикенд надпревара в Blood Castle',
                'excerpt_en' => 'Form your strongest party and compete for the fastest successful Blood Castle run this weekend.',
                'excerpt_bg' => 'Съберете най-силното си party и се състезавайте за най-бързо успешно преминаване на Blood Castle този уикенд.',
                'body_en' => "This weekend the server community is heading to Blood Castle. Every successful run counts, but only coordinated parties will reach the fallen archangel in time.\n\nCheck the Events page before entering so your party is ready for the next opening. Final participation rules and rewards will be announced by the server team before the competition begins.",
                'body_bg' => "Този уикенд общността на сървъра се отправя към Blood Castle. Всяко успешно преминаване е важно, но само добре организираните party групи ще достигнат падналия архангел навреме.\n\nПроверете страницата Events преди участие, за да бъде групата ви готова за следващото отваряне. Окончателните правила и награди ще бъдат обявени от екипа преди началото на състезанието.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'server-stability-update',
                'type' => NewsPostType::News,
                'title_en' => 'Server stability update',
                'title_bg' => 'Обновяване на стабилността',
                'excerpt_en' => 'Recent maintenance improves game-server stability and prepares the platform for upcoming features.',
                'excerpt_bg' => 'Последната профилактика подобрява стабилността и подготвя платформата за следващите функции.',
                'body_en' => "The latest maintenance cycle includes stability improvements for the game server and website services. Character, ranking and event information should continue to update normally.\n\nIf you notice an unexpected account or character issue, sign out of the game and contact the support team with the character name and approximate time of the problem. Never include your password in a support message.",
                'body_bg' => "Последната профилактика включва подобрения по стабилността на игровия сървър и уеб услугите. Информацията за герои, класации и евенти трябва да продължи да се обновява нормално.\n\nАко забележите неочакван проблем с акаунт или герой, излезте от играта и се свържете с поддръжката, като посочите името на героя и приблизителния час. Никога не изпращайте паролата си.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
        ];
    }

    /**
     * Returns practical bilingual starter guides for the classic server version.
     *
     * @return list<array<string, mixed>>
     */
    private function guides(DateTimeInterface $publishedAt, string $serverName): array
    {
        return [
            [
                'slug' => 'getting-started',
                'type' => NewsPostType::Guide,
                'title_en' => 'Getting started on '.$serverName,
                'title_bg' => 'Първи стъпки в '.$serverName,
                'excerpt_en' => 'From registration and client download to your first levels, party and equipment upgrades.',
                'excerpt_bg' => 'От регистрацията и клиента до първите нива, party група и подобрения на екипировката.',
                'body_en' => "1. Create a website account and download the client from one of the available mirrors.\n\n2. Start the launcher, allow it to finish any updates and enter with the same Account ID.\n\n3. Choose a class that matches your preferred play style. Dark Knights are durable melee fighters, Dark Wizards specialize in magic, Fairy Elves support parties and Magic Gladiators become available according to the server rules.\n\n4. Complete early levels around Lorencia, Noria or Devias, join a party and watch the Events page for the next opening.\n\n5. Use the Account panel only while the character is offline when an operation requires it.",
                'body_bg' => "1. Създайте акаунт в сайта и изтеглете клиента от един от наличните mirror линкове.\n\n2. Стартирайте launcher-а, изчакайте обновяванията и влезте със същия Account ID.\n\n3. Изберете клас според предпочитания си стил. Dark Knight е издръжлив боец, Dark Wizard използва магия, Fairy Elf подпомага party групите, а Magic Gladiator се отключва според правилата на сървъра.\n\n4. Вдигнете първите нива около Lorencia, Noria или Devias, намерете party и следете Events страницата.\n\n5. Използвайте Account панела само когато героят е offline, ако операцията го изисква.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'character-reset-guide',
                'type' => NewsPostType::Guide,
                'title_en' => 'Character reset guide',
                'title_bg' => 'Ръководство за ресет на герой',
                'excerpt_en' => 'Understand reset requirements, preview the result and keep your character safe before confirming.',
                'excerpt_bg' => 'Проверете изискванията, прегледайте резултата и защитете героя си преди потвърждение.',
                'body_en' => "A reset returns a qualified character to the configured starting state and applies the points, costs and inventory policy selected by the server administrator.\n\nBefore confirming, sign the character out of the game and open the Reset tab in the Account panel. The preview shows the expected level, reset count, points and any configured item behaviour before the database is changed.\n\nRead every warning in the confirmation step. Reset requirements can change, so the live preview is the authoritative source for your character.",
                'body_bg' => "Ресетът връща отговарящия на условията герой в настроеното начално състояние и прилага точките, цената и политиката за предмети, избрани от администратора.\n\nПреди потвърждение излезте с героя от играта и отворете Reset таба в Account панела. Preview екранът показва очакваното ниво, броя ресети, точките и поведението на предметите преди промяната в базата.\n\nПрочетете всяко предупреждение при потвърждението. Изискванията могат да се променят, затова live preview-ът е водещият източник за вашия герой.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'protect-your-account',
                'type' => NewsPostType::Guide,
                'title_en' => 'Protect your game account',
                'title_bg' => 'Защитете игровия си акаунт',
                'excerpt_en' => 'Simple habits that protect your characters, items and account access from avoidable loss.',
                'excerpt_bg' => 'Лесни навици, които пазят героите, предметите и достъпа до акаунта ви.',
                'body_en' => "Use a unique password that you do not reuse on forums, email or other game servers. Never send it to another player, Game Master or support representative.\n\nDownload the client only from links published on this website. Avoid unofficial launchers, cheats and tools that ask for your credentials.\n\nKeep access to your registered email secure. If you suspect unauthorized access, change the account password and contact support immediately with the affected character names.",
                'body_bg' => "Използвайте уникална парола, която не повтаряте във форуми, имейл или други игрови сървъри. Никога не я изпращайте на друг играч, Game Master или служител на поддръжката.\n\nИзтегляйте клиента само от линковете в този уебсайт. Избягвайте неофициални launcher-и, cheat програми и инструменти, които искат данните ви за вход.\n\nПазете достъпа до регистрирания си имейл. При съмнение за чужд достъп сменете паролата и незабавно се свържете с поддръжката, като посочите засегнатите герои.",
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
        ];
    }

    /**
     * Returns three visible placeholder mirrors for the same full client package.
     *
     * @return list<array<string, mixed>>
     */
    private function downloads(
        DateTimeInterface $publishedAt,
        string $serverName,
        string $serverVersion,
    ): array {
        $mirrors = [
            ['number' => 1, 'region_en' => 'Europe', 'region_bg' => 'Европа'],
            ['number' => 2, 'region_en' => 'North America', 'region_bg' => 'Северна Америка'],
            ['number' => 3, 'region_en' => 'Backup', 'region_bg' => 'Резервен'],
        ];
        $downloads = [];

        foreach ($mirrors as $index => $mirror) {
            $mirrorNumber = $mirror['number'];

            $downloads[] = [
                'slug' => 'full-client-mirror-'.$mirrorNumber,
                'category' => DownloadCategory::Client,
                'title_en' => 'Full Client — Mirror '.$mirrorNumber,
                'title_bg' => 'Пълен клиент — Mirror '.$mirrorNumber,
                'description_en' => "Complete {$serverName} client hosted on the ".$mirror['region_en'].' mirror. Replace this placeholder URL with the verified production package before launch.',
                'description_bg' => "Пълен {$serverName} клиент, разположен на ".$mirror['region_bg'].' mirror. Заменете примерния URL с проверения production пакет преди старта.',
                'version' => $serverVersion,
                'file_size' => '615 MB',
                'download_url' => 'https://downloads.example.com/dreammu/full-client-mirror-'.$mirrorNumber.'.zip',
                'checksum_sha256' => null,
                'sort_order' => $index + 1,
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ];
        }

        return $downloads;
    }
}

<?php

namespace Database\Seeders;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\NewsPost;
use Illuminate\Database\Seeder;

/**
 * Seeds the editable bilingual legal pages required by the public website.
 */
final class ContentPageSeeder extends Seeder
{
    /**
     * Creates missing legal pages without overwriting administrator revisions.
     */
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            NewsPost::query()->firstOrCreate(
                ['slug' => $page['slug']],
                $page,
            );
        }
    }

    /**
     * Returns the initial English and Bulgarian legal content.
     *
     * @return list<array<string, mixed>>
     */
    private function pages(): array
    {
        $publishedAt = now();

        return [
            [
                'slug' => 'cookies',
                'type' => NewsPostType::Page,
                'title_en' => 'Cookie Policy',
                'title_bg' => 'Политика за бисквитките',
                'excerpt_en' => 'How this website uses essential cookies and browser storage to keep accounts secure and remember your choices.',
                'excerpt_bg' => 'Как този уебсайт използва задължителни бисквитки и локално хранилище за сигурност и запомняне на избора ви.',
                'body_en' => <<<'TEXT'
Last updated: 3 November 2026

What are cookies?
Cookies are small text records stored by your browser. They allow a website to maintain a secure session between page requests and protect forms against unauthorized submissions.

Cookies used by this website
This website currently uses only cookies required for its operation:

• Session cookie — keeps your language, sign-in session and temporary form messages available while you browse. It expires according to the server session settings or when the session is invalidated.
• CSRF protection cookie — helps confirm that account and administration forms were submitted from this website.

The cookie notice choice is stored in your browser under “mu_cookie_notice_v1”. Local storage is not transmitted automatically with every request and is used only to avoid showing the same notice again.

Optional tracking
The website does not currently use advertising, behavioural profiling or third-party analytics cookies. If optional cookies are introduced later, this policy and the preference controls must be updated before they are enabled.

Managing cookies
You can remove cookies and local storage through your browser settings. Blocking essential cookies may prevent sign-in, language selection and protected forms from working correctly.

Questions
For questions about cookies used by this server, contact the server operator through the official support channels published on this website.
TEXT,
                'body_bg' => <<<'TEXT'
Последна актуализация: 3 ноември 2026 г.

Какво са бисквитките?
Бисквитките са малки текстови записи, които браузърът съхранява. Те позволяват на уебсайта да поддържа защитена сесия между отделните заявки и да предпазва формите от неоторизирано изпращане.

Бисквитки, използвани от този уебсайт
В момента уебсайтът използва само бисквитки, необходими за работата му:

• Сесийна бисквитка — запазва избрания език, сесията за вход и временните съобщения от формите, докато разглеждате сайта. Изтича според настройките на сървърната сесия или при нейното прекратяване.
• Бисквитка за CSRF защита — помага да се потвърди, че формите за акаунт и администрация са изпратени от този уебсайт.

Изборът за известието за бисквитки се пази локално в браузъра под името „mu_cookie_notice_v1“. Локалното хранилище не се изпраща автоматично с всяка заявка и се използва единствено, за да не показваме същото известие отново.

Незадължително проследяване
В момента уебсайтът не използва рекламни, профилиращи или външни аналитични бисквитки. Ако в бъдеще бъдат добавени незадължителни бисквитки, тази политика и контролите за избор трябва да бъдат актуализирани преди активирането им.

Управление на бисквитките
Можете да изтриете бисквитките и локалното хранилище от настройките на браузъра. Блокирането на задължителните бисквитки може да попречи на входа, избора на език и защитените форми да работят правилно.

Въпроси
За въпроси относно използваните бисквитки се свържете с оператора на сървъра чрез официалните канали за поддръжка, публикувани в този уебсайт.
TEXT,
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'privacy-policy',
                'type' => NewsPostType::Page,
                'title_en' => 'Privacy Policy',
                'title_bg' => 'Политика за поверителност',
                'excerpt_en' => 'What account, gameplay and security information is processed when you use the server and its website.',
                'excerpt_bg' => 'Какви данни за акаунта, играта и сигурността се обработват при използване на сървъра и неговия уебсайт.',
                'body_en' => <<<'TEXT'
Last updated: 3 November 2026

Who is responsible for your data?
The MuOnline server operator is responsible for personal data processed through this website and the connected game services. InterWeb Ltd. is credited as the website creator and is not identified by that credit alone as the server operator or data controller.

Information we process
Depending on the features you use, the service may process your account ID, email address, game credentials, IP address and security logs; character, guild, inventory, warehouse, ranking and gameplay information; website settings, support correspondence and records of account operations such as resets, market activity, votes or virtual-currency transfers.

Why the information is used
Information is used to create and authenticate accounts, provide game and website features, preserve character progress, prevent abuse and fraud, investigate technical or security incidents, enforce the Terms of Use and meet applicable legal obligations.

Public game information
Character names, classes, levels, resets, guild membership, ranking positions and other configured gameplay statistics may be displayed publicly. Account credentials, email addresses and private inventory details are not intended for public display.

Sharing, retention and security
Infrastructure, email, security and technical-support providers may process data only where needed to operate the service. The operator does not sell personal data. Information is retained only while required for the service, security, disputes and legal duties. Reasonable safeguards are used, but no online service can guarantee absolute security.

Your rights and contact
Subject to applicable law, you may request access, correction, deletion, restriction, portability or object to certain processing. Send requests through the official support channels published on this website. Include enough information to identify the relevant account, but never send your password.
TEXT,
                'body_bg' => <<<'TEXT'
Последна актуализация: 3 ноември 2026 г.

Кой отговаря за вашите данни?
Операторът на MuOnline сървъра отговаря за личните данни, обработвани чрез този уебсайт и свързаните игрови услуги. InterWeb Ltd. е посочена като създател на уебсайта, но само това обозначение не я определя като оператор на сървъра или администратор на данните.

Каква информация обработваме
Според използваните функции услугата може да обработва акаунт ID, имейл адрес, игрови идентификационни данни, IP адрес и записи за сигурност; информация за герои, гилдии, инвентар, склад, класации и прогрес; настройки на сайта, кореспонденция с поддръжката и записи за операции като ресети, пазарни сделки, гласуване или трансфери на виртуална валута.

Защо използваме информацията
Информацията се използва за създаване и удостоверяване на акаунти, предоставяне на игровите и уеб функциите, запазване на прогреса, предотвратяване на злоупотреби и измами, разследване на технически или защитни инциденти, прилагане на Условията за ползване и изпълнение на законови задължения.

Публична игрова информация
Имена на герои, класове, нива, ресети, членство в гилдия, позиции в класации и други конфигурирани игрови статистики могат да бъдат публични. Данни за вход, имейл адреси и непублични детайли за инвентара не са предназначени за публично показване.

Споделяне, срокове и сигурност
Доставчици на инфраструктура, имейл, сигурност и техническа поддръжка могат да обработват данни само доколкото е необходимо за услугата. Операторът не продава лични данни. Информацията се пази само докато е нужна за услугата, сигурността, споровете и законовите задължения. Прилагат се разумни мерки, но никоя онлайн услуга не може да гарантира абсолютна сигурност.

Вашите права и контакт
Съобразно приложимото право можете да поискате достъп, коригиране, изтриване, ограничаване, преносимост или да възразите срещу определено обработване. Изпращайте исканията чрез официалните канали за поддръжка в този уебсайт. Посочете достатъчно информация за акаунта, но никога не изпращайте паролата си.
TEXT,
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
            [
                'slug' => 'terms-of-use',
                'type' => NewsPostType::Page,
                'title_en' => 'Terms of Use',
                'title_bg' => 'Условия за ползване',
                'excerpt_en' => 'The rules that apply to accounts, gameplay, community participation and website services.',
                'excerpt_bg' => 'Правилата за акаунти, игра, участие в общността и използване на уеб услугите.',
                'body_en' => <<<'TEXT'
Last updated: 3 November 2026

Acceptance and accounts
By creating an account, signing in or using the MuOnline server, website or related services, you agree to these terms and the published game rules. Keep your credentials confidential and report suspected unauthorized access promptly. You are responsible for activity performed through your account.

Fair play and community conduct
Do not use cheats, automation, exploits, unauthorized modifications or attempts to interfere with the game, website, databases or other players. Harassment, threats, fraud, spam, unlawful content and attempts to evade sanctions are prohibited. Report vulnerabilities privately instead of exploiting or publishing them.

Characters and virtual items
Characters, Zen, credits, items, rankings and other virtual assets are service features and do not represent legal currency or ownership of server software. Transfers, market listings, resets and other operations follow the configured game rules and may be reversed to correct abuse, duplication or technical errors.

Availability and moderation
The operator may maintain, rebalance, suspend or discontinue features to protect the service or community. Uninterrupted availability is not guaranteed. Authorized staff may warn, mute, block, suspend or terminate accounts and characters that breach these terms or threaten the service.

Intellectual property and liability
MuOnline names, game assets and related trademarks belong to their respective owners. Website code, original design and server-specific content remain protected by applicable rights. To the maximum extent allowed by law, the service is provided without guarantees beyond mandatory consumer rights. Nothing excludes liability that cannot lawfully be excluded.

Changes and contact
These terms may be updated when the service, rules or legal requirements change. The current published version applies from its stated date. Questions, reports and appeals should be sent through the official support channels published on this website.
TEXT,
                'body_bg' => <<<'TEXT'
Последна актуализация: 3 ноември 2026 г.

Приемане и акаунти
Със създаването на акаунт, влизането или използването на MuOnline сървъра, уебсайта и свързаните услуги приемате тези условия и публикуваните правила. Пазете данните си за вход и сигнализирайте своевременно при съмнение за неоторизиран достъп. Вие отговаряте за действията през акаунта си.

Честна игра и поведение
Не използвайте измами, автоматизация, експлойти, неразрешени модификации или опити за намеса в играта, уебсайта, базите данни или други играчи. Забранени са тормоз, заплахи, измами, спам, незаконно съдържание и заобикаляне на санкции. Докладвайте уязвимости по личен канал, вместо да ги използвате или публикувате.

Герои и виртуални предмети
Героите, Zen, кредитите, предметите, класациите и другите виртуални активи са функции на услугата и не представляват законно платежно средство или собственост върху сървърния софтуер. Трансфери, пазарни обяви, ресети и други операции следват настроените игрови правила и могат да бъдат отменени при злоупотреба, дублиране или техническа грешка.

Достъпност и модерация
Операторът може да поддържа, балансира, спира или прекратява функции с цел защита на услугата и общността. Непрекъсната достъпност не е гарантирана. Упълномощените служители могат да предупреждават, заглушават, блокират, спират или прекратяват акаунти и герои, които нарушават условията или застрашават услугата.

Интелектуална собственост и отговорност
Имената MuOnline, игровите материали и свързаните търговски марки принадлежат на съответните им притежатели. Кодът на уебсайта, оригиналният дизайн и специфичното съдържание са защитени от приложимите права. В максималната позволена от закона степен услугата се предоставя без гаранции извън задължителните потребителски права. Нищо не изключва отговорност, която законът не позволява да бъде изключена.

Промени и контакт
Условията могат да бъдат актуализирани при промени в услугата, правилата или законовите изисквания. Текущата публикувана версия се прилага от посочената дата. Въпроси, сигнали и обжалвания се изпращат чрез официалните канали за поддръжка в този уебсайт.
TEXT,
                'status' => NewsPostStatus::Published,
                'published_at' => $publishedAt,
            ],
        ];
    }
}

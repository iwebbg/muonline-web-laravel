<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Exercises local installer configuration and failure recovery without Docker or network access.
 */
class LocalInstallerTest extends TestCase
{
    private string $installationDirectory;

    /**
     * Creates disposable environment files outside the real checkout configuration.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installationDirectory = sys_get_temp_dir().'/muonline-installer-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->installationDirectory);
        File::put($this->installationDirectory.'/.env', <<<'ENV'
APP_NAME="MuOnline Web"
APP_KEY=
APP_DB_BOOTSTRAP=true
APP_DB_ADMIN_PASSWORD=
GAME_DB_ADMIN_PASSWORD=
DB_PASSWORD=replace-with-runtime-password
GAME_DB_PASSWORD=replace-with-game-password
ENV);
        File::put($this->installationDirectory.'/docker.env', "DOCKER_ENV=local\n");
    }

    /**
     * Removes only this test's temporary configuration.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory($this->installationDirectory);

        parent::tearDown();
    }

    /**
     * Preserves literal password punctuation without executing shell or dotenv substitutions.
     */
    public function test_passwords_round_trip_without_interpolation_or_shell_execution(): void
    {
        $password = 'Ss@ "quoted" \\backslash ${APP_NAME} $(touch injected) `touch injected` # apostrophe\'';
        $process = $this->installerProcess(<<<'BASH'
write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD "$TEST_PASSWORD"
BASH, environment: ['TEST_PASSWORD' => $password]);

        $this->assertSame(0, $process->run(), $process->getErrorOutput());
        $configuration = $this->configuration();

        $this->assertSame($password, $configuration['APP_DB_ADMIN_PASSWORD']);
        $this->assertFileDoesNotExist($this->installationDirectory.'/injected');
        $this->assertStringNotContainsString($password, $process->getOutput().$process->getErrorOutput());
        $this->assertSame(0600, fileperms($this->installationDirectory.'/.env') & 0777);
    }

    /**
     * Configures the selected adapter and generates distinct stable runtime credentials on reruns.
     */
    public function test_configuration_preserves_keys_and_runtime_passwords_on_rerun(): void
    {
        $answers = "3\n192.168.1.7\n1433\nMuOnline\nMuOnline_Web\n\n\n\nSecretSqlPassword!\ny\nn\n";
        $process = $this->installerProcess('configure_environment', $answers);
        $this->assertSame(0, $process->run(), $process->getErrorOutput());
        $before = $this->configuration();

        $this->assertSame('darksteam_1_0m', $before['GAME_SERVER_PROFILE']);
        $this->assertSame('darksteam_1_0m', $before['GAME_EVENT_PROFILE']);
        $this->assertSame('darksteam_md5', $before['GAME_ACCOUNT_PASSWORD_SCHEME']);
        $this->assertSame('true', $before['GAME_DB_TRUST_SERVER_CERTIFICATE']);
        $this->assertSame('false', $before['GAME_WRITES_ENABLED']);
        $this->assertSame('true', $before['GAME_DB_READONLY']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}Ss@\z/', $before['DB_PASSWORD']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}Ss@\z/', $before['GAME_DB_PASSWORD']);
        $this->assertNotSame($before['DB_PASSWORD'], $before['GAME_DB_PASSWORD']);
        $this->assertSame(32, strlen(base64_decode(substr($before['APP_KEY'], 7), true)));

        $rerun = $this->installerProcess('configure_environment', $answers);
        $this->assertSame(0, $rerun->run(), $rerun->getErrorOutput());
        $after = $this->configuration();

        foreach (['APP_KEY', 'DB_PASSWORD', 'GAME_DB_PASSWORD'] as $setting) {
            $this->assertSame($before[$setting], $after[$setting]);
        }
    }

    /**
     * Uses native S21 defaults and enables game writes only after an affirmative choice.
     */
    public function test_s21_configuration_uses_separate_databases_and_explicit_write_consent(): void
    {
        $answers = "1\n172.30.176.1\n\n\n\n\n\n\nSecretSqlPassword!\ny\ny\nBattleCore_S21\n";
        $process = $this->installerProcess('configure_environment', $answers);

        $this->assertSame(0, $process->run(), $process->getErrorOutput());
        $configuration = $this->configuration();

        $this->assertSame('mudevs_season_21', $configuration['GAME_SERVER_PROFILE']);
        $this->assertSame('MuOnline_S21', $configuration['GAME_DB_MUONLINE']);
        $this->assertSame('MuOnline_S21_Web', $configuration['DB_DATABASE']);
        $this->assertSame('BattleCore_S21', $configuration['GAME_DB_BATTLE_CORE']);
        $this->assertSame('plaintext', $configuration['GAME_ACCOUNT_PASSWORD_SCHEME']);
        $this->assertSame('true', $configuration['GAME_WRITES_ENABLED']);
        $this->assertSame('false', $configuration['GAME_DB_READONLY']);
    }

    /**
     * Rejects injected dotenv lines without partially replacing an existing secret.
     */
    public function test_multiline_values_are_rejected_without_changing_configuration(): void
    {
        $before = File::get($this->installationDirectory.'/.env');
        $process = $this->installerProcess(<<<'BASH'
write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD "$TEST_PASSWORD"
BASH, environment: ['TEST_PASSWORD' => "password\nGAME_WRITES_ENABLED=true"]);

        $this->assertSame(1, $process->run());
        $this->assertSame($before, File::get($this->installationDirectory.'/.env'));
    }

    /**
     * Keeps resumable bootstrap settings and stops startup after provisioning fails.
     */
    public function test_failed_bootstrap_retains_credentials_and_does_not_start_the_application(): void
    {
        $process = $this->installerProcess(<<<'BASH'
write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD 'TemporarySqlPassword!'
compose() {
    printf '%s\n' "$*" >> "$INSTALL_TEST_DIRECTORY/commands"
    if [ "$*" = 'run --rm --no-deps app true' ]; then
        return 42
    fi
}
check_sql_connection() { return 0; }
install_application
BASH);

        $this->assertSame(42, $process->run());
        $configuration = $this->configuration();
        $commands = File::get($this->installationDirectory.'/commands');

        $this->assertSame('true', $configuration['APP_DB_BOOTSTRAP']);
        $this->assertSame('TemporarySqlPassword!', $configuration['APP_DB_ADMIN_PASSWORD']);
        $this->assertStringNotContainsString('--wait', $commands);
    }

    /**
     * Retires temporary credentials only after provisioning and recreates runtime containers.
     */
    public function test_successful_bootstrap_removes_admin_secrets_before_runtime_start(): void
    {
        $process = $this->installerProcess(<<<'BASH'
write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD 'TemporarySqlPassword!'
write_environment_value "$application_environment_file" GAME_DB_ADMIN_PASSWORD 'OtherTemporaryPassword!'
compose() {
    if [[ "$*" == *'--wait --wait-timeout'* ]]; then
        test "$(environment_value "$application_environment_file" APP_DB_BOOTSTRAP)" = false
        test -z "$(environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD)"
        printf 'runtime started\n'
    fi
}
check_sql_connection() { return 0; }
install_application
BASH, "n\n");

        $this->assertSame(0, $process->run(), $process->getErrorOutput());
        $configuration = $this->configuration();

        $this->assertSame('false', $configuration['APP_DB_BOOTSTRAP']);
        $this->assertSame('', $configuration['APP_DB_ADMIN_PASSWORD']);
        $this->assertSame('', $configuration['GAME_DB_ADMIN_PASSWORD']);
        $this->assertStringContainsString('runtime started', $process->getOutput());
    }

    /**
     * Parses the generated dotenv using the same package as the application.
     *
     * @return array<string, string|null>
     */
    private function configuration(): array
    {
        return Dotenv::createArrayBacked($this->installationDirectory)->load();
    }

    /**
     * Runs real installer functions with isolated files and explicit simulated external commands.
     *
     * @param  array<string, string>  $environment
     */
    private function installerProcess(string $commands, string $input = '', array $environment = []): Process
    {
        $script = <<<'BASH'
source "$INSTALL_SCRIPT"
application_environment_file="$INSTALL_TEST_DIRECTORY/.env"
docker_environment_file="$INSTALL_TEST_DIRECTORY/docker.env"
BASH;

        $process = new Process(
            ['bash', '-c', $script."\n".$commands],
            $this->installationDirectory,
            array_merge($environment, [
                'INSTALL_SCRIPT' => base_path('docker/install.sh'),
                'INSTALL_TEST_DIRECTORY' => $this->installationDirectory,
            ]),
        );
        $process->setInput($input);
        $process->setTimeout(15);

        return $process;
    }
}

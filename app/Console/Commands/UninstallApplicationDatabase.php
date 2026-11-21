<?php

namespace App\Console\Commands;

use App\Installation\ApplicationDatabaseRemoval;
use App\Installation\ApplicationDatabaseRemovalResult;
use App\Installation\Contracts\ApplicationDatabaseUninstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Accepts an explicit uninstall confirmation and credentials only through private stdin.
 */
#[Signature('app:database:uninstall {--force : Explicitly authorize removal of the confirmed web database and login}')]
#[Description('Remove only the configured SQL Server web database and dedicated web login using JSON stdin')]
final class UninstallApplicationDatabase extends Command
{
    /**
     * Validates bounded stdin JSON and emits a single credential-free result object.
     */
    public function handle(ApplicationDatabaseUninstaller $uninstaller): int
    {
        if (! $this->option('force')) {
            return $this->respond(new ApplicationDatabaseRemovalResult(false, 'confirmation_required'));
        }

        try {
            $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
            $contents = stream_get_contents($stream ?? STDIN, 16385);

            if ($contents === false || strlen($contents) > 16384) {
                return $this->respond(new ApplicationDatabaseRemovalResult(false, 'invalid_input'));
            }

            $payload = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);

            if (! is_array($payload) || array_is_list($payload)) {
                return $this->respond(new ApplicationDatabaseRemovalResult(false, 'invalid_input'));
            }

            $validator = Validator::make(['request' => $payload], [
                'request' => ['required', 'array:host,port,database,login,admin_username,admin_password,confirmed'],
                'request.host' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_.:\[\]-]+\z/'],
                'request.port' => ['required', 'integer', 'between:1,65535'],
                'request.database' => ['required', 'string', 'regex:/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/'],
                'request.login' => ['required', 'string', 'regex:/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/'],
                'request.admin_username' => ['required', 'string', 'max:128', 'not_regex:/[\x00-\x1F\x7F]/'],
                'request.admin_password' => ['required', 'string', 'max:4096', 'not_regex:/[\x00-\x1F\x7F]/'],
                'request.confirmed' => ['required', 'boolean'],
            ]);

            if ($validator->fails() || ($payload['confirmed'] ?? null) !== true) {
                return $this->respond(new ApplicationDatabaseRemovalResult(false, 'invalid_input'));
            }

            $removal = new ApplicationDatabaseRemoval(
                host: $payload['host'],
                port: (int) $payload['port'],
                databaseName: $payload['database'],
                loginName: $payload['login'],
                adminUsername: $payload['admin_username'],
                adminPassword: $payload['admin_password'],
            );
            unset($payload, $contents);

            return $this->respond($uninstaller->uninstall($removal));
        } catch (JsonException) {
            return $this->respond(new ApplicationDatabaseRemovalResult(false, 'invalid_input'));
        } catch (Throwable) {
            return $this->respond(new ApplicationDatabaseRemovalResult(false, 'uninstall_failed', retryable: true));
        }
    }

    /**
     * Prints raw JSON without terminal formatting or user-controlled exception text.
     */
    private function respond(ApplicationDatabaseRemovalResult $result): int
    {
        $this->output->writeln(json_encode($result->toArray(), JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return $result->ok ? self::SUCCESS : self::FAILURE;
    }
}

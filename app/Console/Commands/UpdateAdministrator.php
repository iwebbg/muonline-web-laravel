<?php

namespace App\Console\Commands;

use App\Admin\ManageAdministratorIdentity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Updates web identities using explicit options and a private password input channel.
 */
#[Signature('admin:update {id : Administrator ID from admin:list} {--name= : New display name} {--email= : New email} {--active= : Active state, 1 or 0} {--password : Prompt for a new password} {--password-stdin : Read one password line from stdin} {--json : Return machine-readable JSON}')]
#[Description('Update a web administrator without exposing credentials in command arguments')]
final class UpdateAdministrator extends Command
{
    /**
     * Validates supplied options and delegates the transactional identity mutation.
     */
    public function handle(ManageAdministratorIdentity $manager): int
    {
        try {
            $changes = [];

            foreach (['name' => 'name', 'email' => 'email', 'active' => 'is_active'] as $option => $attribute) {
                if ($this->option($option) !== null) {
                    $changes[$attribute] = $this->option($option);
                }
            }

            if ($this->option('password') && $this->option('password-stdin')) {
                throw ValidationException::withMessages(['password' => 'Choose either --password or --password-stdin.']);
            }

            if ($this->option('password-stdin')) {
                $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
                $line = fgets($stream ?? STDIN, 4098);

                if ($line === false || strlen($line) > 4096) {
                    throw ValidationException::withMessages(['password' => 'Provide one password line, up to 4096 bytes, through stdin.']);
                }

                $changes['password'] = rtrim($line, "\r\n");
            } elseif ($this->option('password')) {
                if (! $this->input->isInteractive()) {
                    throw ValidationException::withMessages(['password' => 'Use --password-stdin for non-interactive password changes.']);
                }

                $changes['password'] = (string) $this->secret('New password', false);
                $confirmation = (string) $this->secret('Confirm password', false);

                if ($changes['password'] !== $confirmation) {
                    throw ValidationException::withMessages(['password' => 'The password confirmation does not match.']);
                }
            }

            if ($changes === [] || ! ctype_digit((string) $this->argument('id')) || (int) $this->argument('id') < 1) {
                throw ValidationException::withMessages(['administrator' => 'Provide a positive administrator ID and at least one change.']);
            }

            $identity = $manager->handle((int) $this->argument('id'), $changes);
            $this->output->writeln($this->option('json') ? json_encode(['administrator' => $identity], JSON_THROW_ON_ERROR) : 'Administrator updated successfully.', OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        } catch (QueryException) {
            $errors = ['database' => ['Administrator update failed. Check database availability and identity constraints.']];
        } catch (Throwable) {
            $errors = ['administrator' => ['Administrator update failed. Check the application database and identity data.']];
        }

        $this->output->writeln($this->option('json') ? json_encode(['errors' => $errors], JSON_THROW_ON_ERROR) : implode(' ', array_merge(...array_values($errors))), OutputInterface::OUTPUT_RAW);

        return self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Admin\ManageAdministratorIdentity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Removes an unreferenced web identity after explicit confirmation.
 */
#[Signature('admin:delete {id : Administrator ID from admin:list} {--force : Confirm deletion without an interactive prompt} {--json : Return machine-readable JSON}')]
#[Description('Delete a web administrator while protecting the last active administrator')]
final class DeleteAdministrator extends Command
{
    /**
     * Confirms deletion and preserves database history when references prevent removal.
     */
    public function handle(ManageAdministratorIdentity $manager): int
    {
        try {
            if (! ctype_digit((string) $this->argument('id')) || (int) $this->argument('id') < 1) {
                throw ValidationException::withMessages(['administrator' => 'Provide a positive administrator ID.']);
            }

            if (! $this->option('force') && (! $this->input->isInteractive() || ! $this->confirm('Permanently delete administrator #'.$this->argument('id').'?'))) {
                throw ValidationException::withMessages(['confirmation' => 'Deletion cancelled. Use --force to explicitly confirm non-interactive deletion.']);
            }

            $result = $manager->handle((int) $this->argument('id'), delete: true);
            $this->output->writeln($this->option('json') ? json_encode(['administrator' => $result], JSON_THROW_ON_ERROR) : 'Administrator deleted successfully.', OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        } catch (QueryException) {
            $errors = ['database' => ['Deletion failed. Check database availability; identities referenced by history must be deactivated with admin:update --active=0 instead.']];
        } catch (Throwable) {
            $errors = ['administrator' => ['Administrator deletion failed. Check the application database and identity data.']];
        }

        $this->output->writeln($this->option('json') ? json_encode(['errors' => $errors], JSON_THROW_ON_ERROR) : implode(' ', array_merge(...array_values($errors))), OutputInterface::OUTPUT_RAW);

        return self::FAILURE;
    }
}

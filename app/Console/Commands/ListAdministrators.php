<?php

namespace App\Console\Commands;

use App\Admin\ManageAdministratorIdentity;
use App\Models\Administrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists application identities without exposing password hashes or remember tokens.
 */
#[Signature('admin:list {--json : Return machine-readable JSON}')]
#[Description('List web administrators and Game Masters')]
final class ListAdministrators extends Command
{
    /**
     * Prints stable, password-free identity records for the manager or terminal.
     */
    public function handle(ManageAdministratorIdentity $manager): int
    {
        $rows = [];

        foreach (Administrator::query()->select(['id', 'name', 'email', 'role', 'is_active'])->orderBy('id')->get() as $identity) {
            $rows[] = $manager->snapshot($identity);
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode(['administrators' => $rows], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        } else {
            $this->table(['ID', 'Name', 'Email', 'Role', 'Active'], array_map(
                static function (array $row): array {
                    $row['is_active'] = $row['is_active'] ? 'Yes' : 'No';

                    return array_values($row);
                },
                $rows,
            ));
        }

        return self::SUCCESS;
    }
}

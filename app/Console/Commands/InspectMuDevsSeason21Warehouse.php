<?php

namespace App\Console\Commands;

use App\Game\Repositories\SqlServerMuDevsSeason21WarehouseInspectionRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use UnexpectedValueException;

#[Signature('game:inspect-s21-warehouse {account : Native S21 account identifier} {--vault=0 : Zero for main storage, otherwise exact ExtWarehouse.Number} {--json : Print diagnostic native records as JSON}')]
#[Description('Read S21 warehouse records without creating or modifying game items')]
/**
 * Compares saved S21 items with client tooltips while writes remain disabled.
 */
final class InspectMuDevsSeason21Warehouse extends Command
{
    /**
     * Prints verified common fields and opaque native bytes for one account-owned vault.
     */
    public function handle(SqlServerMuDevsSeason21WarehouseInspectionRepository $inspector): int
    {
        $accountId = (string) $this->argument('account');
        $vaultNumber = (string) $this->option('vault');

        if (trim($accountId) === '' || strlen($accountId) > 10 || ! ctype_digit($vaultNumber)) {
            $this->error('Supply an account identifier of 1–10 bytes and a non-negative native vault number.');

            return self::FAILURE;
        }

        try {
            $records = $inspector->forAccount($accountId, (int) $vaultNumber);
        } catch (InvalidArgumentException|UnexpectedValueException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($records === null) {
            $this->error('No matching S21 warehouse exists for that account and vault.');

            return self::FAILURE;
        }

        $items = [];

        foreach ($records as $record) {
            if (! $record->isEmpty) {
                $items[] = $record->toInspectionArray();
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'vault' => (int) $vaultNumber,
                'storedSlots' => count($records),
                'items' => $items,
            ], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                $item['slot'],
                $item['code'] ?? 'Unverified',
                $item['name'] ?? 'Unknown native item',
                $item['level'],
                $item['width'] === null ? '?' : $item['width'].'x'.$item['height'],
                $item['durability'],
                $item['optionLevel'],
                $item['hasSkill'] ? 'Yes' : 'No',
                $item['hasLuck'] ? 'Yes' : 'No',
                $item['excellentMask'],
                $item['ancientSetName'] ?? '',
                $item['ancientOption'],
                $item['harmonyOption'] ?? '',
                implode(',', $item['socketOptions']),
            ];
        }

        $this->table(['Slot', 'Code', 'Item', 'Level', 'Size', 'Durability', 'Option', 'Skill', 'Luck', 'Excellent mask', 'Ancient set', 'Ancient byte', 'Harmony byte', 'Socket bytes'], $rows);
        $this->info('Read-only inspection: advanced options and extended group identities still require client verification.');

        return self::SUCCESS;
    }
}

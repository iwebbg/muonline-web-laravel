<?php

namespace App\CharacterServices\Actions;

use App\CharacterServices\CharacterClassChangeResolver;
use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterServiceRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\CharacterServiceOperation;
use App\Shop\EconomySettingReader;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Coordinates paid character identity services with durable replay and refunds.
 */
final readonly class ExecuteCharacterService
{
    /**
     * Creates the action with pricing, ownership, wallet and game write boundaries.
     */
    public function __construct(
        private EconomySettingReader $economySettingReader,
        private AccountReadRepository $accountReadRepository,
        private CharacterClassChangeResolver $classChangeResolver,
        private GameVersionProfile $versionProfile,
        private CharacterServiceRepository $characterServiceRepository,
        private AdjustWalletBalance $adjustWalletBalance,
    ) {}

    /**
     * Purchases and applies one character rename.
     */
    public function rename(
        string $accountId,
        string $characterName,
        string $newName,
        string $idempotencyKey,
    ): CharacterServiceOperation {
        $this->ensureWritesAreEnabled();
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof CharacterServiceOperation) {
            return $this->perform($this->resolveExisting(
                $existing,
                $this->requestHash(
                    $accountId,
                    CharacterServiceType::Rename,
                    $characterName,
                    $newName,
                    null,
                ),
            ));
        }

        $settings = $this->economySettingReader->current();

        if (! $settings->characterRenameEnabled) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
        }

        if (strcasecmp($characterName, $newName) === 0) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::SameName);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            return $this->resumeExisting($idempotencyKey, $accountId, CharacterServiceType::Rename, $characterName, $newName, null);
        }

        $operation = $this->operation(
            idempotencyKey: $idempotencyKey,
            accountId: $accountId,
            type: CharacterServiceType::Rename,
            characterName: $characterName,
            requestedName: $newName,
            targetClassCode: null,
            priceCredits: $settings->characterRenamePriceCredits,
            beforeSnapshot: ['name' => $characterName, 'class_code' => $character->classCode],
        );

        return $this->perform($operation);
    }

    /**
     * Purchases and applies one family-level class change.
     */
    public function changeClass(
        string $accountId,
        string $characterName,
        string $targetFamily,
        string $idempotencyKey,
    ): CharacterServiceOperation {
        $this->ensureWritesAreEnabled();
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof CharacterServiceOperation) {
            $targetDefinition = $this->versionProfile->classForCode((int) $existing->target_class_code);

            if ($targetDefinition === null || $targetDefinition->family !== $targetFamily) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
            }

            return $this->perform($this->resolveExisting(
                $existing,
                $this->requestHash(
                    $accountId,
                    CharacterServiceType::ClassChange,
                    $characterName,
                    null,
                    (int) $existing->target_class_code,
                ),
            ));
        }

        $settings = $this->economySettingReader->current();

        if (! $settings->characterClassChangeEnabled) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }

        $targetClass = $this->classChangeResolver->resolve($character->classCode, $targetFamily);
        $operation = $this->operation(
            idempotencyKey: $idempotencyKey,
            accountId: $accountId,
            type: CharacterServiceType::ClassChange,
            characterName: $characterName,
            requestedName: null,
            targetClassCode: $targetClass->code,
            priceCredits: $settings->characterClassChangePriceCredits,
            beforeSnapshot: ['name' => $characterName, 'class_code' => $character->classCode],
        );

        return $this->perform($operation);
    }

    /**
     * Creates one pending operation or resolves a compatible replay.
     *
     * @param  array<string, int|string>  $beforeSnapshot
     */
    private function operation(
        string $idempotencyKey,
        string $accountId,
        CharacterServiceType $type,
        string $characterName,
        ?string $requestedName,
        ?int $targetClassCode,
        int $priceCredits,
        array $beforeSnapshot,
    ): CharacterServiceOperation {
        $requestHash = $this->requestHash($accountId, $type, $characterName, $requestedName, $targetClassCode);
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof CharacterServiceOperation) {
            return $this->resolveExisting($existing, $requestHash);
        }

        $operation = CharacterServiceOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'request_hash' => $requestHash,
                'account_id' => $accountId,
                'service_type' => $type,
                'character_name' => $characterName,
                'requested_name' => $requestedName,
                'target_class_code' => $targetClassCode,
                'status' => CharacterOperationStatus::Pending,
                'price_credits' => $priceCredits,
                'settings_snapshot' => [
                    'economy_revision' => $this->economySettingReader->revision(),
                    'game_profile' => (string) config('game.profile.key'),
                ],
                'before_snapshot' => $beforeSnapshot,
            ],
        );

        return $this->resolveExisting($operation, $requestHash);
    }

    /**
     * Debits, mutates and completes one pending operation, refunding rejected writes.
     */
    private function perform(CharacterServiceOperation $operation): CharacterServiceOperation
    {
        if ($operation->status === CharacterOperationStatus::Completed) {
            return $operation;
        }

        if ($operation->status === CharacterOperationStatus::Failed) {
            throw new CharacterServiceRejected(
                CharacterServiceBlockReason::tryFrom((string) $operation->failure_code)
                    ?? CharacterServiceBlockReason::OperationInProgress,
            );
        }

        if ($operation->price_credits > 0 && $operation->wallet_transaction_id === null) {
            try {
                $debit = $this->adjustWalletBalance->execute(
                    accountId: $operation->account_id,
                    currency: WalletCurrency::Credits,
                    type: WalletTransactionType::Purchase,
                    amount: -$operation->price_credits,
                    reason: 'Character service: '.$operation->service_type->value,
                    administratorId: null,
                    idempotencyKey: $this->walletKey($operation->idempotency_key, 'debit'),
                );
                $operation->update(['wallet_transaction_id' => $debit->getKey()]);
            } catch (WalletAdjustmentRejected $exception) {
                $reason = $exception->reason === WalletAdjustmentBlockReason::InsufficientBalance
                    ? CharacterServiceBlockReason::InsufficientCredits
                    : CharacterServiceBlockReason::IdempotencyConflict;
                $operation->update([
                    'status' => CharacterOperationStatus::Failed,
                    'failure_code' => $reason->value,
                    'completed_at' => now(),
                ]);

                throw new CharacterServiceRejected($reason);
            }
        }

        try {
            $result = $operation->service_type === CharacterServiceType::Rename
                ? $this->characterServiceRepository->rename(
                    $operation->account_id,
                    $operation->character_name,
                    (string) $operation->requested_name,
                )
                : $this->characterServiceRepository->changeClass(
                    $operation->account_id,
                    $operation->character_name,
                    (int) $operation->target_class_code,
                );
        } catch (Throwable $exception) {
            $this->refund($operation);
            $reason = $exception instanceof CharacterServiceRejected
                ? $exception->reason->value
                : 'unexpected_error';
            $operation->update([
                'status' => CharacterOperationStatus::Failed,
                'failure_code' => $reason,
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update([
            'status' => CharacterOperationStatus::Completed,
            'after_snapshot' => $result->after,
            'failure_code' => null,
            'completed_at' => now(),
        ]);

        return $operation->refresh();
    }

    /**
     * Refunds a paid operation exactly once after a rejected game mutation.
     */
    private function refund(CharacterServiceOperation $operation): void
    {
        if ($operation->price_credits === 0 || $operation->wallet_transaction_id === null) {
            return;
        }

        $refund = $this->adjustWalletBalance->execute(
            accountId: $operation->account_id,
            currency: WalletCurrency::Credits,
            type: WalletTransactionType::Refund,
            amount: $operation->price_credits,
            reason: 'Character service refund: '.$operation->service_type->value,
            administratorId: null,
            idempotencyKey: $this->walletKey($operation->idempotency_key, 'refund'),
        );
        $operation->update(['refund_wallet_transaction_id' => $refund->getKey()]);
    }

    /**
     * Resolves a rename replay after the old name is no longer readable.
     */
    private function resumeExisting(
        string $idempotencyKey,
        string $accountId,
        CharacterServiceType $type,
        string $characterName,
        ?string $requestedName,
        ?int $targetClassCode,
    ): CharacterServiceOperation {
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();

        if (! $existing instanceof CharacterServiceOperation) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }

        return $this->perform($this->resolveExisting(
            $existing,
            $this->requestHash($accountId, $type, $characterName, $requestedName, $targetClassCode),
        ));
    }

    /**
     * Resolves one compatible replay or rejects key reuse with different input.
     */
    private function resolveExisting(CharacterServiceOperation $operation, string $requestHash): CharacterServiceOperation
    {
        if (! hash_equals($operation->request_hash, $requestHash)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
        }

        return $operation;
    }

    /**
     * Rejects mutations unless both game write switches are open.
     */
    private function ensureWritesAreEnabled(): void
    {
        if (! config('game.writes_enabled', false) || config('database.connections.game.readonly', true)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::WritesDisabled, 503);
        }
    }

    /**
     * Produces one request fingerprint independent of mutable pricing settings.
     */
    private function requestHash(
        string $accountId,
        CharacterServiceType $type,
        string $characterName,
        ?string $requestedName,
        ?int $targetClassCode,
    ): string {
        return hash('sha256', json_encode([
            'account_id' => $accountId,
            'service_type' => $type->value,
            'character_name' => $characterName,
            'requested_name' => $requestedName,
            'target_class_code' => $targetClassCode,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Derives a deterministic ledger key for one side of the operation.
     */
    private function walletKey(string $idempotencyKey, string $side): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'muonline-character-service:'.$idempotencyKey.':'.$side,
        )->toString();
    }
}

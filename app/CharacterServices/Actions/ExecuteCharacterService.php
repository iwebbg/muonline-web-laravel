<?php

namespace App\CharacterServices\Actions;

use App\CharacterServices\CharacterClassChangeResolver;
use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\CharacterServices\PurchasedResetPolicy;
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
use App\Reset\InventoryPolicy;
use App\Reset\ResetProfileReader;
use App\Shop\EconomySettingReader;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Coordinates paid character identity services with durable replay and refunds.
 */
final readonly class ExecuteCharacterService
{
    /** @var list<int> */
    private const PACKAGE_COUNTS = [1, 5, 10];

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
        private ResetProfileReader $resetProfileReader,
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
            requestedAmount: null,
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
                    null,
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
            requestedAmount: null,
            priceCredits: $settings->characterClassChangePriceCredits,
            beforeSnapshot: ['name' => $characterName, 'class_code' => $character->classCode],
        );

        return $this->perform($operation);
    }

    /**
     * Purchases a supported reset package without Zen cost or reset rewards.
     */
    public function purchaseResets(
        string $accountId,
        string $characterName,
        int $resetCount,
        string $idempotencyKey,
    ): CharacterServiceOperation {
        $this->ensureWritesAreEnabled();
        $this->ensurePackageCountIsSupported($resetCount);
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();
        $requestHash = $this->requestHash(
            $accountId,
            CharacterServiceType::ResetPurchase,
            $characterName,
            null,
            null,
            $resetCount,
        );

        if ($existing instanceof CharacterServiceOperation) {
            return $this->perform($this->resolveExisting($existing, $requestHash));
        }

        $settings = $this->economySettingReader->current();

        if (! $settings->characterResetPurchaseEnabled) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }

        $resetSettings = $this->resetProfileReader->current();
        $policy = PurchasedResetPolicy::fromResetSettings($resetSettings);

        if ($policy->inventoryPolicy !== InventoryPolicy::KeepAll) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ResetPolicyUnsupported);
        }

        if ($character->resets + $resetCount > $policy->maximumResets) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::MaximumResetsReached);
        }

        $operation = $this->operation(
            idempotencyKey: $idempotencyKey,
            accountId: $accountId,
            type: CharacterServiceType::ResetPurchase,
            characterName: $characterName,
            requestedName: null,
            targetClassCode: null,
            requestedAmount: $resetCount,
            priceCredits: $settings->resetPurchasePrice($resetCount),
            beforeSnapshot: [
                'name' => $characterName,
                'class_code' => $character->classCode,
                'level' => $character->level,
                'resets' => $character->resets,
                'points' => $character->points,
            ],
            additionalSettingsSnapshot: [
                'reset_revision' => $this->resetProfileReader->revision(),
                'purchased_reset_policy' => $policy->toSnapshot(),
            ],
        );

        return $this->perform($operation);
    }

    /**
     * Purchases one supported package of unallocated LevelUpPoints.
     */
    public function purchaseLevelUpPoints(
        string $accountId,
        string $characterName,
        int $packageCount,
        string $idempotencyKey,
    ): CharacterServiceOperation {
        $this->ensureWritesAreEnabled();
        $this->ensurePackageCountIsSupported($packageCount);
        $existing = CharacterServiceOperation::query()->where('idempotency_key', $idempotencyKey)->first();
        $settings = $this->economySettingReader->current();
        $pointCount = $existing instanceof CharacterServiceOperation
            ? (int) $existing->requested_amount
            : $settings->levelUpPointsPurchaseAmount($packageCount);
        $requestHash = $this->requestHash(
            $accountId,
            CharacterServiceType::LevelUpPointsPurchase,
            $characterName,
            null,
            null,
            $pointCount,
        );

        if ($existing instanceof CharacterServiceOperation) {
            if ((int) ($existing->settings_snapshot['package_count'] ?? 0) !== $packageCount) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
            }

            return $this->perform($this->resolveExisting($existing, $requestHash));
        }

        if (! $settings->characterLevelUpPointsPurchaseEnabled) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }

        if ($character->points + $pointCount > 2_147_483_647) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::PointLimitExceeded);
        }

        $operation = $this->operation(
            idempotencyKey: $idempotencyKey,
            accountId: $accountId,
            type: CharacterServiceType::LevelUpPointsPurchase,
            characterName: $characterName,
            requestedName: null,
            targetClassCode: null,
            requestedAmount: $pointCount,
            priceCredits: $settings->levelUpPointsPurchasePrice($packageCount),
            beforeSnapshot: [
                'name' => $characterName,
                'class_code' => $character->classCode,
                'points' => $character->points,
            ],
            additionalSettingsSnapshot: [
                'package_count' => $packageCount,
                'package_size' => $settings->characterLevelUpPointsPackageSize,
            ],
        );

        return $this->perform($operation);
    }

    /**
     * Creates one pending operation or resolves a compatible replay.
     *
     * @param  array<string, int|string>  $beforeSnapshot
     * @param  array<string, mixed>  $additionalSettingsSnapshot
     */
    private function operation(
        string $idempotencyKey,
        string $accountId,
        CharacterServiceType $type,
        string $characterName,
        ?string $requestedName,
        ?int $targetClassCode,
        ?int $requestedAmount,
        int $priceCredits,
        array $beforeSnapshot,
        array $additionalSettingsSnapshot = [],
    ): CharacterServiceOperation {
        $requestHash = $this->requestHash(
            $accountId,
            $type,
            $characterName,
            $requestedName,
            $targetClassCode,
            $requestedAmount,
        );
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
                'requested_amount' => $requestedAmount,
                'status' => CharacterOperationStatus::Pending,
                'price_credits' => $priceCredits,
                'settings_snapshot' => [
                    'economy_revision' => $this->economySettingReader->revision(),
                    'game_profile' => (string) config('game.profile.key'),
                    ...$additionalSettingsSnapshot,
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
            $result = match ($operation->service_type) {
                CharacterServiceType::Rename => $this->characterServiceRepository->rename(
                    $operation->account_id,
                    $operation->character_name,
                    (string) $operation->requested_name,
                ),
                CharacterServiceType::ClassChange => $this->characterServiceRepository->changeClass(
                    $operation->account_id,
                    $operation->character_name,
                    (int) $operation->target_class_code,
                ),
                CharacterServiceType::ResetPurchase => $this->characterServiceRepository->purchaseResets(
                    $operation->account_id,
                    $operation->character_name,
                    (int) $operation->requested_amount,
                    PurchasedResetPolicy::fromSnapshot(
                        (array) $operation->settings_snapshot['purchased_reset_policy'],
                    ),
                ),
                CharacterServiceType::LevelUpPointsPurchase => $this->characterServiceRepository->purchaseLevelUpPoints(
                    $operation->account_id,
                    $operation->character_name,
                    (int) $operation->requested_amount,
                ),
            };
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
            $this->requestHash($accountId, $type, $characterName, $requestedName, $targetClassCode, null),
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
        ?int $requestedAmount,
    ): string {
        return hash('sha256', json_encode([
            'account_id' => $accountId,
            'service_type' => $type->value,
            'character_name' => $characterName,
            'requested_name' => $requestedName,
            'target_class_code' => $targetClassCode,
            'requested_amount' => $requestedAmount,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Rejects internal calls that do not use one of the public package sizes.
     */
    private function ensurePackageCountIsSupported(int $packageCount): void
    {
        if (! in_array($packageCount, self::PACKAGE_COUNTS, true)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidQuantity);
        }
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

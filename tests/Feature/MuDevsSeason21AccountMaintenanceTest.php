<?php

namespace Tests\Feature;

use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountActivationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountCredentialRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountRegistrationRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Exercises S21 account maintenance without importing legacy VIP or billing columns.
 */
class MuDevsSeason21AccountMaintenanceTest extends TestCase
{
    /**
     * Creates a disposable account table with the native required account fields.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->game()->unprepared('CREATE TABLE MEMB_INFO (
            memb_guid INTEGER PRIMARY KEY AUTOINCREMENT,
            memb___id VARCHAR(10) NOT NULL UNIQUE,
            memb__pwd VARCHAR(10) NOT NULL,
            memb_name VARCHAR(10) NOT NULL,
            sno__numb CHAR(18) NOT NULL CHECK(length(sno__numb) = 18),
            mail_addr VARCHAR(50),
            bloc_code CHAR(1) NOT NULL,
            ctl1_code CHAR(1) NOT NULL,
            AccountLevel INT NOT NULL,
            AccountExpireDate DATETIME NOT NULL
        )');
    }

    /**
     * Registers a blocked account with complete native defaults and no billing row.
     */
    public function test_registration_and_activation_use_native_account_fields(): void
    {
        $result = $this->app->make(SqlServerMuDevsSeason21AccountRegistrationRepository::class)
            ->register('owner', 'secret', 'owner@example.test');

        $account = $this->game()->table('MEMB_INFO')->first();
        $this->assertSame(1, $result->accountGuid);
        $this->assertFalse($result->billingRecordCreated);
        $this->assertSame('1', $account->bloc_code);
        $this->assertSame(0, $account->AccountLevel);
        $this->assertSame('1900-01-01 00:00:00', $account->AccountExpireDate);
        $this->assertSame(18, strlen($account->sno__numb));
        $this->assertTrue($this->app->make(SqlServerMuDevsSeason21AccountActivationRepository::class)->activate('owner'));
        $this->assertSame('0', $this->game()->table('MEMB_INFO')->value('bloc_code'));
        $this->assertFalse($this->app->make(SqlServerMuDevsSeason21AccountActivationRepository::class)->activate('missing'));
    }

    /**
     * Rejects duplicate registration without replacing the original credentials.
     */
    public function test_duplicate_registration_preserves_the_existing_account(): void
    {
        $repository = $this->app->make(SqlServerMuDevsSeason21AccountRegistrationRepository::class);
        $repository->register('owner', 'original', 'owner@example.test');

        try {
            $repository->register('owner', 'replacement', 'other@example.test');
            $this->fail('Duplicate registration should be rejected.');
        } catch (AccountRegistrationRejected) {
            $this->assertSame(1, $this->game()->table('MEMB_INFO')->count());
            $this->assertSame('original', $this->game()->table('MEMB_INFO')->value('memb__pwd'));
        }
    }

    /**
     * Requires the current password before changing credentials and preserves account VIP.
     */
    public function test_credential_update_revalidates_password_and_preserves_account_level(): void
    {
        $this->app->make(SqlServerMuDevsSeason21AccountRegistrationRepository::class)
            ->register('owner', 'secret', 'owner@example.test');
        $this->game()->table('MEMB_INFO')->update(['AccountLevel' => 3]);
        $repository = $this->app->make(SqlServerMuDevsSeason21AccountCredentialRepository::class);

        try {
            $repository->update('owner', 'wrong', 'other@example.test', 'changed');
            $this->fail('An incorrect current password should be rejected.');
        } catch (AccountCredentialUpdateRejected) {
            $this->assertSame('secret', $this->game()->table('MEMB_INFO')->value('memb__pwd'));
        }

        $result = $repository->update('owner', 'secret', 'other@example.test', 'changed');
        $this->assertTrue($result->emailChanged);
        $this->assertTrue($result->passwordChanged);
        $this->assertSame('changed', $this->game()->table('MEMB_INFO')->value('memb__pwd'));
        $this->assertSame(3, $this->game()->table('MEMB_INFO')->value('AccountLevel'));
    }

    /**
     * Scopes password recovery to the matching account and normalized email.
     */
    public function test_password_recovery_does_not_update_a_different_account(): void
    {
        $registration = $this->app->make(SqlServerMuDevsSeason21AccountRegistrationRepository::class);
        $registration->register('owner', 'original', 'owner@example.test');
        $registration->register('other', 'untouched', 'other@example.test');
        $repository = $this->app->make(SqlServerMuDevsSeason21AccountPasswordRecoveryRepository::class);

        $this->assertNull($repository->findRecipient('owner', 'other@example.test'));
        $this->assertNotNull($repository->findRecipient('owner', 'OWNER@example.test'));
        $repository->replacePassword('owner', 'changed');
        $this->assertSame('changed', $this->game()->table('MEMB_INFO')->where('memb___id', 'owner')->value('memb__pwd'));
        $this->assertSame('untouched', $this->game()->table('MEMB_INFO')->where('memb___id', 'other')->value('memb__pwd'));
    }

    /**
     * Returns the isolated in-memory game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}

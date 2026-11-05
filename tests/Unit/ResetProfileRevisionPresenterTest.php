<?php

namespace Tests\Unit;

use App\Models\ResetProfileRevision;
use App\Reset\ResetProfileRevisionPresenter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Verifies reset-profile audit snapshots become readable revision changes.
 */
final class ResetProfileRevisionPresenterTest extends TestCase
{
    /**
     * Confirms unchanged fields are omitted and stored values receive readable labels.
     */
    public function test_presenter_describes_only_changed_settings(): void
    {
        $this->app->setLocale('en');
        $revision = new ResetProfileRevision([
            'revision' => 2,
            'before_snapshot' => [
                'enabled' => true,
                'requiredLevel' => 350,
                'maximumResets' => 999,
                'pointsMode' => 'fixed',
            ],
            'after_snapshot' => [
                'enabled' => false,
                'requiredLevel' => 450,
                'maximumResets' => 999,
                'pointsMode' => 'cumulative',
            ],
        ]);
        $revisions = new Collection([$revision]);

        $presented = (new ResetProfileRevisionPresenter)->present($revisions);

        $this->assertSame(2, $presented[0]['revision']);
        $this->assertSame([
            [
                'label' => 'Enabled',
                'before' => 'Enabled',
                'after' => 'Disabled',
            ],
            [
                'label' => 'Required level',
                'before' => '350',
                'after' => '450',
            ],
            [
                'label' => 'Points calculation',
                'before' => 'Fixed points each reset',
                'after' => 'Points × reset number',
            ],
        ], $presented[0]['changes']);
    }
}

<?php

namespace Tests\Unit;

use App\Models\ModuleProfileRevision;
use App\Modules\ModuleProfileRevisionPresenter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Verifies module audit snapshots become readable availability changes.
 */
final class ModuleProfileRevisionPresenterTest extends TestCase
{
    /**
     * Confirms unchanged modules are omitted and changed switches are localized.
     */
    public function test_presenter_describes_only_changed_modules(): void
    {
        $this->app->setLocale('en');
        $revision = new ModuleProfileRevision([
            'revision' => 2,
            'before_snapshot' => [
                'registration' => true,
                'vote_rewards' => true,
                'market' => false,
            ],
            'after_snapshot' => [
                'registration' => true,
                'vote_rewards' => false,
                'market' => true,
            ],
        ]);
        $revisions = new Collection([$revision]);

        $presented = (new ModuleProfileRevisionPresenter)->present($revisions);

        $this->assertSame(2, $presented[0]['revision']);
        $this->assertSame([
            [
                'label' => 'Vote rewards',
                'before' => 'Enabled',
                'after' => 'Disabled',
            ],
            [
                'label' => 'Player market',
                'before' => 'Disabled',
                'after' => 'Enabled',
            ],
        ], $presented[0]['changes']);
    }
}

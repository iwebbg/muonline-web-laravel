<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResetProfileRevision;
use App\Reset\ResetProfileReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * Displays the administrator control center with persisted reset settings.
 */
final class DashboardController extends Controller
{
    /**
     * Displays the current typed policy and recent immutable revision history.
     */
    public function __invoke(ResetProfileReader $resetProfileReader): View
    {
        $recentRevisions = $resetProfileReader->recentRevisions();

        return view('admin.dashboard', [
            'resetSettings' => $resetProfileReader->current()->toClientArray(),
            'resetRevision' => $resetProfileReader->revision(),
            'resetRevisions' => $this->summarizeRevisions($recentRevisions),
        ]);
    }

    /**
     * Reduces audit models to the non-sensitive fields rendered by the interface.
     *
     * @param  Collection<int, ResetProfileRevision>  $revisions
     * @return list<array{revision: int, changedAt: string}>
     */
    private function summarizeRevisions(Collection $revisions): array
    {
        $summaries = [];

        foreach ($revisions as $revision) {
            $summaries[] = [
                'revision' => $revision->revision,
                'changedAt' => $revision->created_at?->diffForHumans() ?? '',
            ];
        }

        return $summaries;
    }
}

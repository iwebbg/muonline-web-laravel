<?php

namespace App\Modules;

use App\Models\ModuleProfileRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converts module profile snapshots into administrator-facing switch changes.
 */
final class ModuleProfileRevisionPresenter
{
    /**
     * Presents recent revisions with only the module switches that changed.
     *
     * @param  Collection<int, ModuleProfileRevision>  $revisions
     * @return list<array{revision: int, createdAt: string, changes: list<array{label: string, before: string, after: string}>}>
     */
    public function present(Collection $revisions): array
    {
        $presentedRevisions = [];

        foreach ($revisions as $revision) {
            $presentedRevisions[] = [
                'revision' => $revision->revision,
                'createdAt' => $revision->created_at?->diffForHumans() ?? '',
                'changes' => $this->changesFor($revision),
            ];
        }

        return $presentedRevisions;
    }

    /**
     * Extracts changed module switches in their stable server-module order.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(ModuleProfileRevision $revision): array
    {
        $beforeSnapshot = $revision->before_snapshot ?? [];
        $afterSnapshot = $revision->after_snapshot ?? [];
        $changes = [];

        foreach (ServerModule::cases() as $module) {
            $beforeEnabled = (bool) ($beforeSnapshot[$module->value] ?? false);
            $afterEnabled = (bool) ($afterSnapshot[$module->value] ?? false);

            if ($beforeEnabled === $afterEnabled) {
                continue;
            }

            $changes[] = [
                'label' => __('ui.admin.module_labels.'.$module->value),
                'before' => $this->availabilityLabel($beforeEnabled),
                'after' => $this->availabilityLabel($afterEnabled),
            ];
        }

        return $changes;
    }

    /**
     * Returns a localized label for one module availability state.
     */
    private function availabilityLabel(bool $enabled): string
    {
        return $enabled
            ? __('ui.admin.revision_value_enabled')
            : __('ui.admin.revision_value_disabled');
    }
}

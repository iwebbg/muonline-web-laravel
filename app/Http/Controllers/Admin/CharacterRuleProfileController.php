<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCharacterRuleProfileRequest;
use App\Models\Administrator;
use App\Reset\Actions\UpdateResetProfile;
use App\Reset\ResetProfileReader;
use App\Reset\ResetProfileRevisionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Displays and updates one independently editable character-rule profile.
 */
final class CharacterRuleProfileController extends Controller
{
    /** @var list<string> */
    private const PROFILES = ['reset', 'grand-reset', 'stat-reset', 'pk-clear', 'move-to-town'];

    /**
     * Displays one focused profile form backed by the active settings revision.
     */
    public function edit(
        string $profile,
        ResetProfileReader $resetProfileReader,
        ResetProfileRevisionPresenter $revisionPresenter,
    ): View {
        $this->abortUnlessKnownProfile($profile);
        $recentRevisions = $resetProfileReader->recentRevisions();

        return view('admin.character-rules', [
            'profile' => $profile,
            'settings' => $resetProfileReader->current()->toClientArray(),
            'revision' => $resetProfileReader->revision(),
            'revisions' => $revisionPresenter->present($recentRevisions),
        ]);
    }

    /**
     * Applies one profile section immediately while preserving every other section.
     */
    public function update(
        UpdateCharacterRuleProfileRequest $request,
        string $profile,
        ResetProfileReader $resetProfileReader,
        UpdateResetProfile $updateResetProfile,
    ): JsonResponse|RedirectResponse {
        $this->abortUnlessKnownProfile($profile);
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $settings = $request->settings($resetProfileReader->current());
        $savedProfile = $updateResetProfile->handle(
            settings: $settings,
            administrator: $administrator,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.admin.character_rule_profile_applied'),
                'revision' => $savedProfile->revision,
                'settings' => $settings->toClientArray(),
            ]);
        }

        return redirect()
            ->route('admin.character-rules.edit', ['profile' => $profile])
            ->with('status', __('ui.admin.character_rule_profile_applied'));
    }

    /**
     * Rejects unknown profile slugs before selecting a view partial or validation branch.
     */
    private function abortUnlessKnownProfile(string $profile): void
    {
        abort_unless(in_array($profile, self::PROFILES, true), 404);
    }
}

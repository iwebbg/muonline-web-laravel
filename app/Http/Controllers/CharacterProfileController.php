<?php

namespace App\Http\Controllers;

use App\Game\Contracts\RealmReadRepository;
use Illuminate\View\View;

/**
 * Displays one version-normalized public character profile.
 */
class CharacterProfileController extends Controller
{
    /**
     * Returns the public profile or a non-enumerating not-found response.
     */
    public function __invoke(
        string $characterName,
        RealmReadRepository $realmReadRepository,
    ): View {
        $character = $realmReadRepository->publicCharacter($characterName);

        abort_if($character === null, 404);

        return view('profiles.character', ['character' => $character]);
    }
}

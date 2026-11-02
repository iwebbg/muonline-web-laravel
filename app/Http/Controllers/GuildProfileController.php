<?php

namespace App\Http\Controllers;

use App\Game\Contracts\RealmReadRepository;
use Illuminate\View\View;

/**
 * Displays one version-normalized public guild profile and roster.
 */
class GuildProfileController extends Controller
{
    /**
     * Returns the public guild or a non-enumerating not-found response.
     */
    public function __invoke(
        string $guildName,
        RealmReadRepository $realmReadRepository,
    ): View {
        $guild = $realmReadRepository->publicGuild($guildName);

        abort_if($guild === null, 404);

        return view('profiles.guild', ['guild' => $guild]);
    }
}

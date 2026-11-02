<?php

namespace App\Game\Data;

/**
 * Carries one public guild identity and its normalized roster.
 */
final readonly class PublicGuildProfile
{
    /**
     * Creates a public guild record from the version-specific schema adapter.
     *
     * @param  list<PublicGuildMember>  $members
     */
    public function __construct(
        public string $name,
        public string $master,
        public int $score,
        public string $notice,
        public array $members,
    ) {}
}

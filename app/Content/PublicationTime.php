<?php

namespace App\Content;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Converts administrator-local publication times to and from UTC storage.
 */
final class PublicationTime
{
    /**
     * Converts one timezone-less browser value into the UTC instant stored by the application.
     */
    public function fromAdministratorInput(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $localDateTime,
            $this->administratorTimezone(),
        )->utc();
    }

    /**
     * Formats one stored UTC instant for a timezone-less administrator browser field.
     */
    public function forAdministratorInput(DateTimeInterface $publicationTime): string
    {
        return CarbonImmutable::instance($publicationTime)
            ->setTimezone($this->administratorTimezone())
            ->format('Y-m-d\TH:i');
    }

    /**
     * Returns the configured server-local timezone used by content administrators.
     */
    private function administratorTimezone(): string
    {
        return (string) config('game.content_timezone');
    }
}

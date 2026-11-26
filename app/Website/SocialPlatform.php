<?php

namespace App\Website;

/** Identifies the supported social networks and their bundled brand icons. */
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Twitter = 'x';
    case Vimeo = 'vimeo';
    case Youtube = 'youtube';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Discord = 'discord';
    case Twitch = 'twitch';
    case Telegram = 'telegram';
    case Reddit = 'reddit';
    case Whatsapp = 'whatsapp';
    case Bluesky = 'bluesky';
    case Threads = 'threads';
    case Steam = 'steam';

    /** Returns the network's public brand name. */
    public function label(): string
    {
        return match ($this) {
            self::Twitter => 'X / Twitter',
            self::Youtube => 'YouTube',
            self::Tiktok => 'TikTok',
            self::Whatsapp => 'WhatsApp',
            default => ucfirst($this->value),
        };
    }
}

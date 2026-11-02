<?php

namespace App\Content;

/**
 * Identifies the purpose of one published server download.
 */
enum DownloadCategory: string
{
    case Client = 'client';
    case Patch = 'patch';
    case Tool = 'tool';
    case Other = 'other';
}

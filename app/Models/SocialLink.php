<?php

namespace App\Models;

use App\Website\SocialPlatform;
use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Stores one public community link; a network may have multiple destinations. */
#[Fillable(['type', 'label', 'url', 'sort_order'])]
final class SocialLink extends Model
{
    /** @use HasFactory<SocialLinkFactory> */
    use HasFactory;

    /** Casts the allow-listed network and its display order.
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SocialPlatform::class,
            'sort_order' => 'integer',
        ];
    }
}

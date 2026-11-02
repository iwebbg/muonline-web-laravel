<?php

namespace Database\Factories;

use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title),
            'category' => DownloadCategory::Other,
            'title_en' => $title,
            'title_bg' => null,
            'description_en' => fake()->paragraph(),
            'description_bg' => null,
            'version' => null,
            'file_size' => null,
            'download_url' => 'https://downloads.example.com/'.Str::slug($title).'.zip',
            'checksum_sha256' => null,
            'sort_order' => 0,
            'status' => NewsPostStatus::Draft,
            'published_at' => null,
            'created_by_administrator_id' => null,
            'updated_by_administrator_id' => null,
        ];
    }

    /**
     * Marks the generated download as publicly visible.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => NewsPostStatus::Published,
            'published_at' => now()->subMinute(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsPost>
 */
final class NewsPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'slug' => Str::slug($title),
            'type' => NewsPostType::News,
            'title_en' => $title,
            'title_bg' => null,
            'excerpt_en' => fake()->sentence(14),
            'excerpt_bg' => null,
            'body_en' => fake()->paragraphs(4, true),
            'body_bg' => null,
            'status' => NewsPostStatus::Draft,
            'published_at' => null,
            'created_by_administrator_id' => null,
            'updated_by_administrator_id' => null,
        ];
    }

    /**
     * Marks the generated article as publicly visible at a deterministic time.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => NewsPostStatus::Published,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Places the generated article in the public guide collection.
     */
    public function guide(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NewsPostType::Guide,
        ]);
    }

    /**
     * Places the generated entry in the public standalone page collection.
     */
    public function page(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NewsPostType::Page,
        ]);
    }
}

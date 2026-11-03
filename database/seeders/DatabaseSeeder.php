<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds safe public defaults without creating privileged identities.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Seeds public defaults while leaving privileged identities empty.
     */
    public function run(): void
    {
        $this->call(GameServerSeeder::class);
        $this->call(ContentPageSeeder::class);
        $this->call(DemoContentSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use App\Domain\AiAudit\Models\AiRequest;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);

        if (! AiRequest::query()->exists()) {
            $this->call(AiRequestSeeder::class);
        }
    }
}

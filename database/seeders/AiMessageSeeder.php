<?php

namespace Database\Seeders;

use App\Models\AiMessage;
use Illuminate\Database\Seeder;

class AiMessageSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        AiMessage::factory()->create();
    }
}

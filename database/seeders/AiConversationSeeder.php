<?php

namespace Database\Seeders;

use App\Models\AiConversation;
use Illuminate\Database\Seeder;

class AiConversationSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        AiConversation::factory()->create();
    }
}

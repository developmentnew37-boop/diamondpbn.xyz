<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE links MODIFY url VARCHAR(2048) NOT NULL');
        DB::statement('ALTER TABLE campaign_links MODIFY url VARCHAR(2048) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE links MODIFY url VARCHAR(500) NOT NULL');
        DB::statement('ALTER TABLE campaign_links MODIFY url VARCHAR(500) NOT NULL');
    }
};

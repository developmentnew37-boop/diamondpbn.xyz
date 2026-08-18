<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_sites', function (Blueprint $table) {
            $table->boolean('block_inspect')->nullable()->after('last_health_error');
            $table->timestamp('block_inspect_synced_at')->nullable()->after('block_inspect');
            $table->boolean('block_inspect_supported')->default(true)->after('block_inspect_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('wp_sites', function (Blueprint $table) {
            $table->dropColumn(['block_inspect', 'block_inspect_synced_at', 'block_inspect_supported']);
        });
    }
};

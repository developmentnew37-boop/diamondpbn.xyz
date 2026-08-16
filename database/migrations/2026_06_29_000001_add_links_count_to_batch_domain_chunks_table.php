<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_domain_chunks', function (Blueprint $table) {
            $table->unsignedSmallInteger('links_count')->default(0)->after('links_payload');
        });

        DB::table('batch_domain_chunks')->update([
            'links_count' => DB::raw('JSON_LENGTH(links_payload)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('batch_domain_chunks', function (Blueprint $table) {
            $table->dropColumn('links_count');
        });
    }
};

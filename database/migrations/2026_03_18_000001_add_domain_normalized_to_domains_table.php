<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('domain_normalized')->nullable()->after('domain');
        });

        // Backfill normalized values (best-effort).
        $rows = DB::table('domains')->select('id', 'domain')->orderBy('id')->get();
        foreach ($rows as $row) {
            $d = strtolower(trim((string) ($row->domain ?? '')));
            $d = preg_replace('#^https?://#i', '', $d) ?? $d;
            $d = preg_replace('#^www\.#i', '', $d) ?? $d;
            $d = rtrim($d, "/ \t\n\r\0\x0B");
            DB::table('domains')->where('id', $row->id)->update([
                'domain_normalized' => $d,
            ]);
        }

        // Make it non-null + unique per user.
        Schema::table('domains', function (Blueprint $table) {
            $table->string('domain_normalized')->nullable(false)->change();
            $table->unique(['user_id', 'domain_normalized'], 'domains_user_domain_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropUnique('domains_user_domain_normalized_unique');
            $table->dropColumn('domain_normalized');
        });
    }
};


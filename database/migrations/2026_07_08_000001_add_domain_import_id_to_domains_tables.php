<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('domain_import_id')->nullable()->after('user_id')->constrained('domain_imports')->nullOnDelete();
        });

        Schema::table('campaign_domains', function (Blueprint $table) {
            $table->foreignId('domain_import_id')->nullable()->after('user_id')->constrained('domain_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_import_id');
        });

        Schema::table('campaign_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_import_id');
        });
    }
};

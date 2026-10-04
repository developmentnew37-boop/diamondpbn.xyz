<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_site_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_normalized', 80);
            $table->timestamps();

            $table->unique('name_normalized');
        });

        Schema::table('wp_sites', function (Blueprint $table) {
            $table->foreignId('wp_site_category_id')
                ->nullable()
                ->after('wp_site_import_id')
                ->constrained('wp_site_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wp_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wp_site_category_id');
        });

        Schema::dropIfExists('wp_site_categories');
    }
};

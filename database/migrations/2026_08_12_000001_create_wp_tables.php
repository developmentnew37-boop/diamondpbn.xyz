<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_site_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('wp_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wp_site_import_id')->nullable()->constrained('wp_site_imports')->nullOnDelete();
            $table->string('domain');
            $table->string('domain_normalized')->index();
            $table->string('api_url', 500);
            $table->string('api_key')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_health_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'domain_normalized']);
        });

        Schema::create('wp_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_links')->default(0);
            $table->unsignedInteger('total_domains')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wp_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_batch_id')->constrained('wp_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('keyword');
            $table->boolean('no_follow')->default(false);
            $table->string('link_type', 20)->default('text');
            $table->json('extra_data')->nullable();
            $table->timestamps();
        });

        Schema::create('wp_batch_site_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_batch_id')->constrained('wp_batches')->cascadeOnDelete();
            $table->foreignId('wp_site_id')->constrained('wp_sites')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->json('links_payload');
            $table->unsignedSmallInteger('links_count')->default(0);
            $table->json('results_payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('success_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['wp_batch_id', 'wp_site_id', 'chunk_index']);
            $table->index(['wp_batch_id', 'status']);
            $table->index(['wp_site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_batch_site_chunks');
        Schema::dropIfExists('wp_links');
        Schema::dropIfExists('wp_batches');
        Schema::dropIfExists('wp_sites');
        Schema::dropIfExists('wp_site_imports');
    }
};

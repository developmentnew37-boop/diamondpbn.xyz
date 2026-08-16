<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chunk-based batch domain links (DB-optimized).
     * One row per chunk of up to 100 links per domain.
     * Remote site sends webhook with per-link results.
     */
    public function up(): void
    {
        Schema::create('batch_domain_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index')->default(0); // 0, 1, 2... per domain
            $table->json('links_payload'); // [{url, keyword, nofollow}, ...] max 100
            $table->json('results_payload')->nullable(); // [{status, remote_post_id?, error?}, ...] from webhook
            $table->string('status', 20)->default('pending'); // pending|processing|completed|partial
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('success_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable(); // chunk-level error
            $table->timestamps();

            $table->unique(['batch_id', 'domain_id', 'chunk_index']);
            $table->index(['batch_id', 'status']);
            $table->index(['domain_id', 'status']);
            $table->index(['status', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_domain_chunks');
    }
};

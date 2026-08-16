<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batch_domain_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('remote_post_id')->nullable();
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['domain_id', 'status']);
            $table->index('link_id');
            $table->index(['status', 'attempts']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_domain_links');
    }
};

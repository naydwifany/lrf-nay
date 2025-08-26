<?php
// database/migrations/2024_01_04_000000_create_document_comments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->onDelete('cascade');
            $table->string('user_nik');
            $table->string('user_name');
            $table->string('user_role');
            $table->text('comment');
            $table->boolean('is_forum_closed')->default(false);
            $table->timestamp('forum_closed_at')->nullable();
            $table->string('forum_closed_by_nik')->nullable();
            $table->string('forum_closed_by_name')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['document_request_id', 'created_at']);
            $table->index(['user_nik']);
            $table->index(['is_forum_closed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
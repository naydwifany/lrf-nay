<?php
// database/migrations/2024_01_11_000000_create_forum_diskusi_status_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_diskusi_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lrf_doc_id')->constrained('document_requests')->onDelete('cascade');
            $table->string('nik_direksi');
            $table->string('nama_direksi')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['lrf_doc_id', 'is_active']);
            $table->index(['nik_direksi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_diskusi_status');
    }
};
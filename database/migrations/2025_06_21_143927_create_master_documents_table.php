<?php
// database/migrations/2024_01_10_000000_create_master_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_name');
            $table->string('document_code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('required_fields')->nullable(); // fields yang wajib diisi
            $table->json('optional_fields')->nullable(); // fields yang opsional
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active']);
            $table->index(['document_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_documents');
    }
};
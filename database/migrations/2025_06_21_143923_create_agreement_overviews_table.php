<?php
// database/migrations/2024_01_05_000000_create_agreement_overviews_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_overviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->nullable()->constrained()->onDelete('cascade');
            
            // User information (dari session)
            $table->string('nik');
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('divisi')->nullable();
            $table->string('direktorat')->nullable();
            $table->string('level')->nullable();
            
            // Agreement information
            $table->string('nomor_dokumen')->unique();
            $table->date('tanggal_ao');
            $table->string('pic')->nullable();
            $table->string('counterparty');
            $table->text('deskripsi')->nullable();
            
            // Director information
            $table->string('nama_direksi_default')->nullable(); // Direksi 1 (dari divisi)
            $table->string('nama_direksi'); // Direksi 2 (pilihan)
            $table->string('nik_direksi')->nullable();
            
            // Time range
            $table->date('start_date_jk');
            $table->date('end_date_jk');
            
            // Terms and conditions
            $table->text('resume')->nullable();
            $table->text('ketentuan_dan_mekanisme')->nullable();
            
            // JSON fields for complex data
            $table->json('parties')->nullable();
            $table->json('terms')->nullable();
            $table->json('risks')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'submitted', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->boolean('is_draft')->default(true);
            
            // Relationship to LRF
            $table->foreignId('lrf_doc_id')->nullable();
            
            // Timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['nik', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['nomor_dokumen']);
            $table->index(['lrf_doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_overviews');
    }
};
<?php
// database/migrations/2024_01_02_000000_create_document_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            
            // User information
            $table->string('nik');
            $table->string('nik_atasan')->nullable();
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('divisi')->nullable();
            $table->string('unit_bisnis')->nullable();
            $table->string('dept')->nullable();
            $table->string('direktorat')->nullable();
            $table->string('seksi')->nullable();
            $table->string('subseksi')->nullable();
            $table->json('data')->nullable();
            
            // Document information
            $table->string('nomor_dokumen')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('tipe_dokumen')->nullable(); // UBAH: hapus constraint sementara
            $table->enum('doc_filter', ['review', 'create', 'others'])->default('review');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', [
                'draft',
                'submitted',
                'pending_supervisor',
                'pending_gm',
                'pending_legal',
                'discussion',
                'agreement_creation',
                'agreement_approval',
                'completed',
                'rejected'
            ])->default('draft');
            $table->boolean('is_draft')->default(true);
            
            // File uploads
            $table->string('dokumen_utama')->nullable();
            $table->string('akta_pendirian')->nullable();
            $table->string('ktp_direktur')->nullable();
            $table->string('akta_perubahan')->nullable();
            $table->string('surat_kuasa')->nullable();
            $table->string('npwp')->nullable();
            $table->string('nib')->nullable();
            
            // Rich text fields
            $table->longText('lama_perjanjian_surat')->nullable();
            $table->longText('syarat_ketentuan_pembayaran')->nullable();
            $table->longText('kewajiban_mitra')->nullable();
            $table->longText('hak_mitra')->nullable();
            $table->longText('kewajiban_eci')->nullable();
            $table->longText('hak_eci')->nullable();
            $table->longText('pajak')->nullable();
            $table->longText('ketentuan_lain')->nullable();
            
            // Timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['nik', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['is_draft']);
            $table->index(['nomor_dokumen']);
            $table->index(['tipe_dokumen']); // Index untuk foreign key nanti
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
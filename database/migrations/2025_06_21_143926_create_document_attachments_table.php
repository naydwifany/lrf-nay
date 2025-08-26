<?php
// database/migrations/2024_01_09_000000_create_document_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type'); // DocumentRequest, AgreementOverview
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // pdf, doc, xlsx, etc.
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('uploaded_by_nik');
            $table->string('uploaded_by_name');
            $table->enum('attachment_type', [
                'dokumen_utama',
                'akta_pendirian',
                'ktp_direktur', 
                'akta_perubahan',
                'surat_kuasa',
                'npwp',
                'nib',
                'supporting_document',
                'other'
            ])->default('other');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['uploaded_by_nik']);
            $table->index(['attachment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
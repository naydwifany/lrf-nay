<?php
// database/migrations/2024_01_12_000000_add_foreign_keys_to_document_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Tambahkan foreign key setelah tabel master_documents ada
            $table->foreign('tipe_dokumen')->references('id')->on('master_documents')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['tipe_dokumen']);
        });
    }
};
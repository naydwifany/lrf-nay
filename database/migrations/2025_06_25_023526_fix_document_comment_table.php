<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Cek apakah tabel document_comments sudah ada
        if (Schema::hasTable('document_comments')) {
            Schema::table('document_comments', function (Blueprint $table) {
                // Tambahkan kolom-kolom yang belum ada dengan nullable
                if (!Schema::hasColumn('document_comments', 'user_nik')) {
                    $table->string('user_nik')->nullable()->after('user_id');
                }
                
                if (!Schema::hasColumn('document_comments', 'user_role')) {
                    $table->string('user_role')->nullable()->after('user_nik');
                }
                
                if (!Schema::hasColumn('document_comments', 'parent_id')) {
                    $table->foreignId('parent_id')->nullable()->after('user_role')
                          ->constrained('document_comments')->onDelete('cascade');
                }
                
            });
        } else {
            // Jika tabel belum ada, buat tabel baru lengkap
            Schema::create('document_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_request_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('user_nik')->nullable();
                $table->string('user_role')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('document_comments')->onDelete('cascade');
                $table->text('comment');
                $table->string('attachment')->nullable();
                $table->boolean('is_forum_closed')->default(false);
                $table->timestamps();

                $table->index(['document_request_id', 'created_at']);
                $table->index(['document_request_id', 'parent_id']);
                $table->index(['is_forum_closed']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('document_comments')) {
            Schema::table('document_comments', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn(['user_nik', 'user_role', 'parent_id', 'is_forum_closed']);
            });
        }
    }
};
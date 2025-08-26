<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('document_request_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('document_request_comments')->onDelete('cascade');
            $table->boolean('is_resolved')->default(false)->after('attachment');
            $table->foreignId('resolved_by')->nullable()->after('is_resolved')->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            
            $table->index(['document_request_id', 'parent_id']);
            $table->index(['is_resolved']);
        });
    }

    public function down()
    {
        Schema::table('document_request_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['parent_id', 'is_resolved', 'resolved_by', 'resolved_at']);
        });
    }
};
<?php

// database/migrations/2024_01_05_000000_add_discussion_fields_to_document_comments.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            // Add missing fields untuk discussion features
            $table->foreignId('parent_id')->nullable()->after('document_request_id')->constrained('document_comments')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->after('document_request_id')->constrained('users')->onDelete('cascade');
            $table->json('attachments')->nullable()->after('comment');
            $table->boolean('is_resolved')->default(false)->after('attachments');
            $table->foreignId('resolved_by')->nullable()->after('is_resolved')->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            
            // Update existing enum untuk user_role
            $table->dropColumn('user_role');
        });
        
        // Add enum column dengan proper values
        DB::statement("ALTER TABLE document_comments ADD COLUMN user_role ENUM(
            'head_legal', 
            'reviewer_legal', 
            'finance', 
            'head_finance',
            'general_manager', 
            'head_general_manager',
            'counterparty',
            'admin',
            'supervisor',
            'manager'
        ) AFTER user_name");
        
        // Add new indexes
        Schema::table('document_comments', function (Blueprint $table) {
            $table->index(['parent_id']);
            $table->index(['user_role', 'created_at']);
            $table->index(['is_resolved', 'user_role']);
        });
    }

    public function down(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn([
                'parent_id', 
                'user_id', 
                'attachments', 
                'is_resolved', 
                'resolved_by', 
                'resolved_at'
            ]);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['user_role', 'created_at']);
            $table->dropIndex(['is_resolved', 'user_role']);
        });
        
        // Revert user_role to string
        DB::statement("ALTER TABLE document_comments DROP COLUMN user_role");
        $table->string('user_role')->after('user_name');
    }
};

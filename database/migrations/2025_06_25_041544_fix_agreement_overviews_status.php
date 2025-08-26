<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('agreement_overviews', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending_head',
                'pending_gm', 
                'pending_finance',
                'pending_legal',
                'pending_director1',
                'pending_director2',
                'approved',
                'rejected',
                'rediscuss'
            ])->default('draft')->after('counterparty');
            
            // Tambah field untuk director selection
            $table->string('director1_nik')->nullable()->after('nik')->comment('Auto from API - same direktorat');
            $table->string('director2_nik')->nullable()->after('director1_nik')->comment('Manual selection');
            $table->string('director1_name')->nullable()->after('director2_nik');
            $table->string('director2_name')->nullable()->after('director1_name');
            
            // Approval tracking fields
            $table->timestamp('approved_by_head_at')->nullable();
            $table->timestamp('approved_by_gm_at')->nullable();
            $table->timestamp('approved_by_finance_at')->nullable();
            $table->timestamp('approved_by_legal_at')->nullable();
            $table->timestamp('approved_by_director1_at')->nullable();
            $table->timestamp('approved_by_director2_at')->nullable();
            $table->timestamp('final_approved_at')->nullable();
            
            // Rejection/rediscuss fields
            $table->text('rejection_reason')->nullable();
            $table->text('rediscuss_reason')->nullable();
            
            $table->index(['status']);
            $table->index(['director1_nik']);
            $table->index(['director2_nik']);
        });
    }

    public function down()
    {
        Schema::table('agreement_overviews', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'director1_nik', 'director2_nik', 'director1_name', 'director2_name',
                'approved_by_head_at', 'approved_by_gm_at', 'approved_by_finance_at', 
                'approved_by_legal_at', 'approved_by_director1_at', 'approved_by_director2_at',
                'final_approved_at', 'rejection_reason', 'rediscuss_reason'
            ]);
        });
    }
};
<?php
// database/migrations/2024_01_03_000000_create_document_approvals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->onDelete('cascade');
            $table->string('approver_nik');
            $table->string('approver_name');
            $table->enum('approval_type', [
                'supervisor', 
                'manager',
                'senior_manager',
                'general_manager', 
                'admin_legal',
                'legal', 
                'head_legal',
                'finance', 
                'head_finance',
                'director'
            ]);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['document_request_id', 'status']);
            $table->index(['approver_nik', 'status']);
            $table->index(['approval_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
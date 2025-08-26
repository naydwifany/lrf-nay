<?php
// database/migrations/2024_01_07_000000_create_notifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_nik');
            $table->string('recipient_name');
            $table->string('sender_nik')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('title');
            $table->text('message');
            $table->enum('type', [
                'approval_request',
                'approval_approved', 
                'approval_rejected',
                'discussion_started',
                'discussion_closed',
                'agreement_created',
                'document_completed'
            ]);
            $table->string('related_type')->nullable(); // DocumentRequest, AgreementOverview
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['recipient_nik', 'is_read']);
            $table->index(['type', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
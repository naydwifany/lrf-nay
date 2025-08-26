<?php

// database/migrations/2024_01_XX_create_document_discussions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('opened_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->boolean('requires_finance_input')->default(true);
            $table->boolean('finance_participated')->default(false);
            $table->timestamps();

            $table->index(['document_request_id', 'status']);
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_discussions');
    }
};


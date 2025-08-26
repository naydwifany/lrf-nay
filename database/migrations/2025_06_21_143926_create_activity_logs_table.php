<?php
// database/migrations/2024_01_08_000000_create_activity_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_nik');
            $table->string('user_name');
            $table->string('user_role');
            $table->string('action'); // created, updated, approved, rejected, etc.
            $table->string('description');
            $table->string('subject_type'); // DocumentRequest, AgreementOverview
            $table->unsignedBigInteger('subject_id');
            $table->json('properties')->nullable(); // old_values, new_values, etc.
            $table->timestamps();
            
            // Indexes
            $table->index(['user_nik', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
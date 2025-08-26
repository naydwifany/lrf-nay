<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add NIK as unique identifier
            $table->string('nik')->unique()->after('id');
            
            // Employee data from API
            $table->string('pegawai_id')->nullable()->after('password');
            $table->string('department')->nullable()->after('pegawai_id');
            $table->string('divisi')->nullable()->after('department');
            $table->string('seksi')->nullable()->after('divisi');
            $table->string('subseksi')->nullable()->after('seksi');
            $table->string('jabatan')->nullable()->after('subseksi');
            $table->string('level')->nullable()->after('jabatan');
            $table->string('level_tier')->nullable()->after('level');
            $table->string('satker_id')->nullable()->after('level_tier');
            $table->string('direktorat')->nullable()->after('satker_id');
            $table->string('unit_name')->nullable()->after('direktorat');
            
            // Hierarchy
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->after('unit_name');
            $table->string('supervisor_nik')->nullable()->after('supervisor_id');
            
            // Role and status
            $table->enum('role', [
                'admin',
                'user', 
                'supervisor',
                'manager',
                'senior_manager',
                'general_manager',
                'head_legal',
                'admin_legal',
                'reviewer_legal',
                'head_finance',
                'finance',
                'director',
                'legal',
                'counterparty'
            ])->default('user')->after('supervisor_nik');
            $table->boolean('is_active')->default(true)->after('role');
            
            // API integration
            $table->text('api_token')->nullable()->after('is_active');
            $table->timestamp('last_api_sync')->nullable()->after('api_token');
            
            // Indexes
            $table->index(['nik', 'is_active']);
            $table->index(['role', 'is_active']);
            $table->index(['supervisor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['supervisor_id']);
            
            // Drop indexes
            $table->dropIndex(['nik', 'is_active']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['supervisor_id']);
            
            // Drop columns
            $table->dropColumn([
                'nik',
                'pegawai_id',
                'department',
                'divisi',
                'seksi',
                'subseksi',
                'jabatan',
                'level',
                'level_tier',
                'satker_id',
                'direktorat',
                'unit_name',
                'supervisor_id',
                'supervisor_nik',
                'role',
                'is_active',
                'api_token',
                'last_api_sync'
            ]);
        });
    }
};
<?php
// database/migrations/2024_06_24_180000_add_missing_columns_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Check and add missing columns
            if (!Schema::hasColumn('users', 'can_access_admin_panel')) {
                $table->boolean('can_access_admin_panel')->default(false)->after('is_active');
            }
            
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('can_access_admin_panel');
            }
            
            if (!Schema::hasColumn('users', 'login_attempts')) {
                $table->integer('login_attempts')->default(0)->after('last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('login_attempts');
            }
            
            if (!Schema::hasColumn('users', 'jabatan')) {
                $table->string('jabatan')->nullable()->after('role');
            }
            
            if (!Schema::hasColumn('users', 'divisi')) {
                $table->string('divisi')->nullable()->after('jabatan');
            }
            
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('divisi');
            }
            
            if (!Schema::hasColumn('users', 'direktorat')) {
                $table->string('direktorat')->nullable()->after('department');
            }
            
            if (!Schema::hasColumn('users', 'level')) {
                $table->integer('level')->nullable()->after('direktorat');
            }
            
            if (!Schema::hasColumn('users', 'supervisor_nik')) {
                $table->string('supervisor_nik', 50)->nullable()->after('level');
            }
            
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('supervisor_nik');
            }
        });

        // Add indexes for better performance
        Schema::table('users', function (Blueprint $table) {
            // Only add indexes if columns exist
            $columns = Schema::getColumnListing('users');
            
            if (in_array('nik', $columns)) {
                $table->index('nik');
            }
            if (in_array('role', $columns)) {
                $table->index('role');
            }
            if (in_array('divisi', $columns)) {
                $table->index('divisi');
            }
            if (in_array('is_active', $columns)) {
                $table->index('is_active');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $indexes = ['nik', 'role', 'divisi', 'is_active'];
            foreach ($indexes as $index) {
                if (Schema::hasColumn('users', $index)) {
                    try {
                        $table->dropIndex(['nik']);
                    } catch (\Exception $e) {
                        // Index might not exist, continue
                    }
                }
            }
            
            // Optionally drop columns (uncomment if needed)
            // $table->dropColumn([
            //     'can_access_admin_panel', 'last_login_at', 'login_attempts', 'notes',
            //     'jabatan', 'divisi', 'department', 'direktorat', 'level', 'supervisor_nik'
            // ]);
        });
    }
};
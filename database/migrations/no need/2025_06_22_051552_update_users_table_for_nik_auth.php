<?php
// database/migrations/2025_01_01_122000_update_users_table_for_nik_auth.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Make NIK unique and primary identifier
            if (!Schema::hasColumn('users', 'nik')) {
                $table->string('nik', 20)->unique()->after('id');
            } else {
                // Ensure NIK is unique if it wasn't before
                $table->unique('nik');
            }
            
            // Make email nullable since we're using NIK for auth
            $table->string('email')->nullable()->change();
            
            // Add auth-related fields if they don't exist
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('last_api_sync');
            }
            
            if (!Schema::hasColumn('users', 'login_attempts')) {
                $table->integer('login_attempts')->default(0)->after('last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('login_attempts');
            }
            
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('locked_until');
            }
            
            if (!Schema::hasColumn('users', 'force_password_change')) {
                $table->boolean('force_password_change')->default(false)->after('password_changed_at');
            }
            
            // Add indexes for authentication performance
            $table->index('nik');
            $table->index(['nik', 'is_active']);
            $table->index(['email', 'is_active']);
            $table->index('last_login_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['nik']);
            $table->dropIndex(['nik', 'is_active']);
            $table->dropIndex(['email', 'is_active']);
            $table->dropIndex(['last_login_at']);
            
            // Remove added columns
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'login_attempts')) {
                $table->dropColumn('login_attempts');
            }
            if (Schema::hasColumn('users', 'locked_until')) {
                $table->dropColumn('locked_until');
            }
            if (Schema::hasColumn('users', 'password_changed_at')) {
                $table->dropColumn('password_changed_at');
            }
            if (Schema::hasColumn('users', 'force_password_change')) {
                $table->dropColumn('force_password_change');
            }
            
            // Make email required again
            $table->string('email')->nullable(false)->change();
            
            // Remove unique constraint from NIK (but keep the column)
            $table->dropUnique(['nik']);
        });
    }
};
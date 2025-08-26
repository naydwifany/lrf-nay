<?php
// database/migrations/xxxx_update_users_for_division_hierarchy.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('users', 'pegawai_id')) {
                $table->string('pegawai_id')->nullable()->after('nik');
            }
            
            if (!Schema::hasColumn('users', 'unit_name')) {
                $table->string('unit_name')->nullable()->after('email');
            }
            
            // Add division hierarchy tracking
            if (!Schema::hasColumn('users', 'division_manager_nik')) {
                $table->string('division_manager_nik')->nullable()->after('supervisor_nik');
            }
            
            if (!Schema::hasColumn('users', 'division_senior_manager_nik')) {
                $table->string('division_senior_manager_nik')->nullable()->after('division_manager_nik');
            }
            
            if (!Schema::hasColumn('users', 'division_general_manager_nik')) {
                $table->string('division_general_manager_nik')->nullable()->after('division_senior_manager_nik');
            }
            
            // API sync tracking
            if (!Schema::hasColumn('users', 'api_data')) {
                $table->json('api_data')->nullable()->after('last_api_sync');
            }
            
            if (!Schema::hasColumn('users', 'hierarchy_last_sync')) {
                $table->timestamp('hierarchy_last_sync')->nullable()->after('api_data');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist
            $columnsToDrop = [];
            
            if (Schema::hasColumn('users', 'pegawai_id')) {
                $columnsToDrop[] = 'pegawai_id';
            }
            if (Schema::hasColumn('users', 'unit_name')) {
                $columnsToDrop[] = 'unit_name';
            }
            if (Schema::hasColumn('users', 'division_manager_nik')) {
                $columnsToDrop[] = 'division_manager_nik';
            }
            if (Schema::hasColumn('users', 'division_senior_manager_nik')) {
                $columnsToDrop[] = 'division_senior_manager_nik';
            }
            if (Schema::hasColumn('users', 'division_general_manager_nik')) {
                $columnsToDrop[] = 'division_general_manager_nik';
            }
            if (Schema::hasColumn('users', 'api_data')) {
                $columnsToDrop[] = 'api_data';
            }
            if (Schema::hasColumn('users', 'hierarchy_last_sync')) {
                $columnsToDrop[] = 'hierarchy_last_sync';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
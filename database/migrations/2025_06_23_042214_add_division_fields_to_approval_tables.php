<?php
// database/migrations/2025_01_01_121000_add_division_fields_to_approval_tables.php (Fixed)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
            return !empty($indexes);
        };

        // Update document_approvals table
        Schema::table('document_approvals', function (Blueprint $table) use ($indexExists) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('document_approvals', 'division_level')) {
                $table->string('division_level')->nullable()->after('approval_type');
            }
            
            if (!Schema::hasColumn('document_approvals', 'is_division_approval')) {
                $table->boolean('is_division_approval')->default(false)->after('division_level');
            }
            
            if (!Schema::hasColumn('document_approvals', 'metadata')) {
                $table->text('metadata')->nullable()->after('comments');
            }
        });

        // Add indexes only if they don't exist
        if (!$indexExists('document_approvals', 'document_approvals_division_level_is_division_approval_index')) {
            DB::statement('ALTER TABLE document_approvals ADD INDEX document_approvals_division_level_is_division_approval_index(division_level, is_division_approval)');
        }
        
        if (!$indexExists('document_approvals', 'document_approvals_approver_nik_status_index')) {
            DB::statement('ALTER TABLE document_approvals ADD INDEX document_approvals_approver_nik_status_index(approver_nik, status)');
        }

        // Update agreement_approvals table
        Schema::table('agreement_approvals', function (Blueprint $table) use ($indexExists) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('agreement_approvals', 'division_level')) {
                $table->string('division_level')->nullable()->after('approval_type');
            }
            
            if (!Schema::hasColumn('agreement_approvals', 'is_division_approval')) {
                $table->boolean('is_division_approval')->default(false)->after('division_level');
            }
            
            if (!Schema::hasColumn('agreement_approvals', 'metadata')) {
                $table->text('metadata')->nullable()->after('comments');
            }
        });

        // Add indexes only if they don't exist
        if (!$indexExists('agreement_approvals', 'agreement_approvals_division_level_is_division_approval_index')) {
            DB::statement('ALTER TABLE agreement_approvals ADD INDEX agreement_approvals_division_level_is_division_approval_index(division_level, is_division_approval)');
        }
        
        if (!$indexExists('agreement_approvals', 'agreement_approvals_approver_nik_status_index')) {
            DB::statement('ALTER TABLE agreement_approvals ADD INDEX agreement_approvals_approver_nik_status_index(approver_nik, status)');
        }

        // Update users table with division hierarchy fields if not exists
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'division_manager_nik')) {
                $table->string('division_manager_nik')->nullable()->after('supervisor_nik');
            }
            
            if (!Schema::hasColumn('users', 'division_senior_manager_nik')) {
                $table->string('division_senior_manager_nik')->nullable()->after('division_manager_nik');
            }
            
            if (!Schema::hasColumn('users', 'division_general_manager_nik')) {
                $table->string('division_general_manager_nik')->nullable()->after('division_senior_manager_nik');
            }
            
            if (!Schema::hasColumn('users', 'hierarchy_last_sync')) {
                $table->timestamp('hierarchy_last_sync')->nullable()->after('last_api_sync');
            }
        });

        // Add indexes for users table only if they don't exist
        if (!$indexExists('users', 'users_division_manager_nik_index')) {
            DB::statement('ALTER TABLE users ADD INDEX users_division_manager_nik_index(division_manager_nik)');
        }
        
        if (!$indexExists('users', 'users_division_senior_manager_nik_index')) {
            DB::statement('ALTER TABLE users ADD INDEX users_division_senior_manager_nik_index(division_senior_manager_nik)');
        }
        
        if (!$indexExists('users', 'users_division_general_manager_nik_index')) {
            DB::statement('ALTER TABLE users ADD INDEX users_division_general_manager_nik_index(division_general_manager_nik)');
        }
        
        if (!$indexExists('users', 'users_divisi_level_index')) {
            DB::statement('ALTER TABLE users ADD INDEX users_divisi_level_index(divisi, level)');
        }
    }

    public function down()
    {
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
            return !empty($indexes);
        };

        // Remove indexes from document_approvals if they exist
        if ($indexExists('document_approvals', 'document_approvals_division_level_is_division_approval_index')) {
            DB::statement('ALTER TABLE document_approvals DROP INDEX document_approvals_division_level_is_division_approval_index');
        }
        
        if ($indexExists('document_approvals', 'document_approvals_approver_nik_status_index')) {
            DB::statement('ALTER TABLE document_approvals DROP INDEX document_approvals_approver_nik_status_index');
        }

        Schema::table('document_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('document_approvals', 'division_level')) {
                $table->dropColumn('division_level');
            }
            if (Schema::hasColumn('document_approvals', 'is_division_approval')) {
                $table->dropColumn('is_division_approval');
            }
            if (Schema::hasColumn('document_approvals', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Remove indexes from agreement_approvals if they exist
        if ($indexExists('agreement_approvals', 'agreement_approvals_division_level_is_division_approval_index')) {
            DB::statement('ALTER TABLE agreement_approvals DROP INDEX agreement_approvals_division_level_is_division_approval_index');
        }
        
        if ($indexExists('agreement_approvals', 'agreement_approvals_approver_nik_status_index')) {
            DB::statement('ALTER TABLE agreement_approvals DROP INDEX agreement_approvals_approver_nik_status_index');
        }

        Schema::table('agreement_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('agreement_approvals', 'division_level')) {
                $table->dropColumn('division_level');
            }
            if (Schema::hasColumn('agreement_approvals', 'is_division_approval')) {
                $table->dropColumn('is_division_approval');
            }
            if (Schema::hasColumn('agreement_approvals', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Remove indexes from users if they exist
        if ($indexExists('users', 'users_divisi_level_index')) {
            DB::statement('ALTER TABLE users DROP INDEX users_divisi_level_index');
        }
        if ($indexExists('users', 'users_division_general_manager_nik_index')) {
            DB::statement('ALTER TABLE users DROP INDEX users_division_general_manager_nik_index');
        }
        if ($indexExists('users', 'users_division_senior_manager_nik_index')) {
            DB::statement('ALTER TABLE users DROP INDEX users_division_senior_manager_nik_index');
        }
        if ($indexExists('users', 'users_division_manager_nik_index')) {
            DB::statement('ALTER TABLE users DROP INDEX users_division_manager_nik_index');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hierarchy_last_sync')) {
                $table->dropColumn('hierarchy_last_sync');
            }
            if (Schema::hasColumn('users', 'division_general_manager_nik')) {
                $table->dropColumn('division_general_manager_nik');
            }
            if (Schema::hasColumn('users', 'division_senior_manager_nik')) {
                $table->dropColumn('division_senior_manager_nik');
            }
            if (Schema::hasColumn('users', 'division_manager_nik')) {
                $table->dropColumn('division_manager_nik');
            }
        });
    }
};
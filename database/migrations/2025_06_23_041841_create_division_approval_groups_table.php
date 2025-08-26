<?php
// database/migrations/2025_01_01_120000_create_division_approval_groups_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('division_approval_groups', function (Blueprint $table) {
            $table->id();
            $table->string('division_code')->unique();
            $table->string('division_name');
            $table->string('direktorat')->nullable();
            $table->string('manager_nik')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('senior_manager_nik')->nullable();
            $table->string('senior_manager_name')->nullable();
            $table->string('general_manager_nik')->nullable();
            $table->string('general_manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('approval_settings')->nullable(); // Custom approval rules per division
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
            
            $table->index(['division_code', 'is_active']);
            $table->index('direktorat');
        });
    }

    public function down()
    {
        Schema::dropIfExists('division_approval_groups');
    }
};
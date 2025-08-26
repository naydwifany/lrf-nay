<?php
// database/migrations/xxxx_add_notification_range_to_master_documents.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('master_documents', function (Blueprint $table) {
            // Notification settings
            $table->json('notification_settings')->nullable()->after('optional_fields');
            $table->boolean('enable_notifications')->default(true)->after('notification_settings');
            
            // Default notification ranges (in days before due date)
            $table->integer('warning_days')->default(7)->after('enable_notifications');
            $table->integer('urgent_days')->default(3)->after('warning_days');
            $table->integer('critical_days')->default(1)->after('urgent_days');
            
            // Notification recipients
            $table->json('notification_recipients')->nullable()->after('critical_days');
            $table->text('notification_message_template')->nullable()->after('notification_recipients');
        });
    }

    public function down()
    {
        Schema::table('master_documents', function (Blueprint $table) {
            $table->dropColumn([
                'notification_settings',
                'enable_notifications', 
                'warning_days',
                'urgent_days',
                'critical_days',
                'notification_recipients',
                'notification_message_template'
            ]);
        });
    }
};
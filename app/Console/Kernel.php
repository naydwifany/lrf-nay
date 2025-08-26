<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [
        \App\Console\Commands\TestApiAuth::class,
        \App\Console\Commands\TestNotifications::class,
        \App\Console\Commands\ProcessDocumentNotifications::class,
        \App\Console\Commands\CleanActivityLogs::class,
        \App\Console\Commands\SyncDivisionApprovalGroups::class,
        \App\Console\Commands\SyncUsersFromApi::class,
        \App\Console\Commands\TestLoginCreateUser::class,
        \App\Console\Commands\CreateGmUser::class,
        \App\Console\Commands\ListUsers::class,
        \App\Console\Commands\TestDiscussionCommand::class,
        \App\Console\Commands\CheckDiscussionCommand::class,
        \App\Console\Commands\CreateTestDiscussionCommand::class,
        \App\Console\Commands\ListUsersCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Document notification processing
        $schedule->command('documents:process-notifications')
             ->twiceDaily(9, 15) // 9 AM and 3 PM
             ->withoutOverlapping()
             ->runInBackground()
             ->appendOutputTo(storage_path('logs/notification-cron.log'));

        // Clean old activity logs (monthly)
        $schedule->command('activitylog:clean')
             ->monthly()
             ->at('02:00')
             ->withoutOverlapping();

        // Sync division approval groups (daily)
        $schedule->command('sync:division-approvals')
             ->daily()
             ->at('06:00')
             ->withoutOverlapping();

        // Send approval reminders (daily)
        $schedule->command('approvals:send-reminders')
             ->dailyAt('08:00')
             ->withoutOverlapping();

        $schedule->command('sync:divisions')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/division-sync.log'));

        // Test koneksi API setiap jam untuk monitoring
        $schedule->command('test:division-api')
                 ->hourly()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/api-health-check.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
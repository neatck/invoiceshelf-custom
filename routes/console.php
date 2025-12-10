<?php

use App\Models\CompanySetting;
use App\Models\FileDisk;
use App\Models\RecurringInvoice;
use App\Space\InstallUtils;
use Illuminate\Support\Facades\Schedule;

// Only run in demo environment
if (config('app.env') === 'demo') {
    Schedule::command('reset:app --force')
        ->daily()
        ->runInBackground()
        ->withoutOverlapping();
}

if (InstallUtils::isDbCreated()) {
    Schedule::command('check:invoices:status')
        ->daily();

    Schedule::command('check:estimates:status')
        ->daily();

    // Wrap in try-catch to handle cases where the database schema is incomplete
    // (e.g., during fresh migrations when deleted_at column doesn't exist yet)
    try {
        $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
        foreach ($recurringInvoices as $recurringInvoice) {
            $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

            Schedule::call(function () use ($recurringInvoice) {
                $recurringInvoice->generateInvoice();
            })->cron($recurringInvoice->frequency)->timezone($timeZone);
        }
    } catch (\Exception $e) {
        // Silently ignore if table structure is incomplete (e.g., during migrations)
        // The scheduler will pick up recurring invoices on the next artisan call
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic S3 Database Backups
    |--------------------------------------------------------------------------
    |
    | Schedule 5 automatic database backups to S3 daily between 2 PM and 10 PM.
    | Times: 2:00 PM, 5:00 PM, 7:30 PM, 8:00 PM, 9:30 PM
    |
    | The 7:30 PM and 8:00 PM backups capture end-of-business data (closing 8-9 PM).
    | Backups only run if internet is available and S3 disk is configured.
    |
    */
    try {
        // Only schedule if an S3 disk is configured
        $hasS3Disk = FileDisk::where('driver', 's3')->exists();
        
        if ($hasS3Disk) {
            // Get company timezone (default to Africa/Nairobi for East Africa)
            $timeZone = CompanySetting::getSetting('time_zone', 1) ?? 'Africa/Nairobi';

            // Backup 1: 2:00 PM - Early afternoon
            Schedule::command('backup:s3-scheduled')
                ->dailyAt('14:00')
                ->timezone($timeZone)
                ->withoutOverlapping()
                ->runInBackground()
                ->description('S3 Backup - 2:00 PM');

            // Backup 2: 5:00 PM - Late afternoon
            Schedule::command('backup:s3-scheduled')
                ->dailyAt('17:00')
                ->timezone($timeZone)
                ->withoutOverlapping()
                ->runInBackground()
                ->description('S3 Backup - 5:00 PM');

            // Backup 3: 7:30 PM - Pre-closing (business closes 8-9 PM)
            Schedule::command('backup:s3-scheduled')
                ->dailyAt('19:30')
                ->timezone($timeZone)
                ->withoutOverlapping()
                ->runInBackground()
                ->description('S3 Backup - 7:30 PM (Pre-closing)');

            // Backup 4: 8:00 PM - At closing time
            Schedule::command('backup:s3-scheduled')
                ->dailyAt('20:00')
                ->timezone($timeZone)
                ->withoutOverlapping()
                ->runInBackground()
                ->description('S3 Backup - 8:00 PM (Closing)');

            // Backup 5: 9:30 PM - Post-closing safety backup
            Schedule::command('backup:s3-scheduled')
                ->dailyAt('21:30')
                ->timezone($timeZone)
                ->withoutOverlapping()
                ->runInBackground()
                ->description('S3 Backup - 9:30 PM (Post-closing)');
        }
    } catch (\Exception $e) {
        // Silently ignore if S3 disk table doesn't exist yet
    }
}

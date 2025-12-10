<?php

namespace App\Console\Commands;

use App\Jobs\CreateBackupJob;
use App\Models\FileDisk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScheduledS3Backup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:s3-scheduled 
                            {--disk-name= : Name of the S3 disk to use (default: first S3 disk found)}
                            {--skip-internet-check : Skip internet connectivity check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled database backup to S3 if internet is available';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check internet connectivity first (unless skipped)
        if (!$this->option('skip-internet-check') && !$this->hasInternetConnection()) {
            $this->warn('No internet connection available. Skipping S3 backup.');
            Log::warning('Scheduled S3 backup skipped: No internet connection');
            return self::FAILURE;
        }

        // Find the S3 disk
        $diskName = $this->option('disk-name');
        
        if ($diskName) {
            $fileDisk = FileDisk::where('driver', 's3')
                ->where('name', $diskName)
                ->first();
        } else {
            // Get first available S3 disk
            $fileDisk = FileDisk::where('driver', 's3')->first();
        }

        if (!$fileDisk) {
            $this->error('No S3 disk configured. Please configure an S3 backup disk in Settings > File Disk.');
            Log::error('Scheduled S3 backup failed: No S3 disk configured');
            return self::FAILURE;
        }

        $this->info("Starting scheduled database backup to S3 disk: {$fileDisk->name}");
        Log::info("Starting scheduled S3 backup to disk: {$fileDisk->name}");

        try {
            // Dispatch the backup job (same as manual backup from UI)
            dispatch(new CreateBackupJob([
                'file_disk_id' => $fileDisk->id,
                'option' => 'only-db',  // Database only for scheduled backups
            ]))->onQueue(config('backup.queue.name'));

            $this->info('Backup job dispatched successfully.');
            Log::info('Scheduled S3 backup job dispatched successfully');
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch backup job: ' . $e->getMessage());
            Log::error('Scheduled S3 backup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Check if internet connection is available by pinging AWS S3 endpoint
     */
    protected function hasInternetConnection(): bool
    {
        try {
            // Try to reach AWS S3's endpoint (lightweight check)
            $response = Http::timeout(5)->get('https://s3.amazonaws.com');
            return $response->successful() || $response->status() === 403; // 403 is expected without auth
        } catch (\Exception $e) {
            // Try a fallback check with Google DNS
            try {
                $response = Http::timeout(5)->get('https://dns.google');
                return $response->successful();
            } catch (\Exception $e) {
                return false;
            }
        }
    }
}

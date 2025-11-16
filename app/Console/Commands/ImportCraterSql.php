<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCraterSql extends Command
{
    protected $signature = 'invoiceshelf:import-crater {path? : Path to SQL dump (defaults to InvoiceShelf/dev db/db-dumps/mysql-crater.sql)}';
    protected $description = 'Import Crater SQL dump into current database (useful for development migration)';

    public function handle(): int
    {
        $defaultPath = base_path('dev db/db-dumps/mysql-crater.sql');
        $path = $this->argument('path') ?: $defaultPath;

        if (! File::exists($path)) {
            $this->error("SQL dump not found: {$path}");
            return self::FAILURE;
        }

        $this->warn('This will run SQL directly against the configured DB. Ensure you are on a DEV database.');
        if (! $this->confirm('Proceed?')) {
            return self::INVALID;
        }

        $sql = File::get($path);
        $this->info('Importing... this may take a while');
        DB::unprepared($sql);
        $this->info('Import complete.');
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateDatabase extends Command
{
    protected $signature = 'db:create';

    protected $description = 'Create database if it does not exist';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            $this->error('Invalid database name.');
            return self::FAILURE;
        }

        $attempts = (int) env('DB_CREATE_ATTEMPTS', 20);
        $retrySeconds = (int) env('DB_CREATE_RETRY_SECONDS', 3);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                DB::connection('mysql')->select('SELECT 1');
                $this->info("Database '{$database}' is ready.");

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            if (! env('DB_CREATE_USERNAME')) {
                $this->error("Database '{$database}' is not reachable by the configured application user.");
                $this->line('Create the database with your database administrator user, or set DB_CREATE_USERNAME and DB_CREATE_PASSWORD for setup only.');
                $this->line("Original connection error: {$lastException->getMessage()}");

                return self::FAILURE;
            }

            try {
                // Connect without database.
                Config::set('database.connections.mysql.database', null);
                Config::set('database.connections.mysql.username', env('DB_CREATE_USERNAME'));
                Config::set('database.connections.mysql.password', env('DB_CREATE_PASSWORD'));

                DB::purge('mysql');
                DB::reconnect('mysql');

                DB::statement("
                    CREATE DATABASE IF NOT EXISTS `$database`
                    CHARACTER SET utf8mb4
                    COLLATE utf8mb4_unicode_ci
                ");

                $this->restoreApplicationConnection($database);

                $this->info("Database '{$database}' is ready.");

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->restoreApplicationConnection($database);

                if ($attempt < $attempts) {
                    $this->line("Database is not ready yet. Retrying in {$retrySeconds}s...");
                    sleep($retrySeconds);
                }
            }
        }

        $this->error("Database '{$database}' could not be created or reached.");
        $this->line("Last connection error: {$lastException?->getMessage()}");

        return self::FAILURE;
    }

    private function restoreApplicationConnection(string $database): void
    {
        Config::set('database.connections.mysql.database', $database);
        Config::set('database.connections.mysql.username', env('DB_USERNAME'));
        Config::set('database.connections.mysql.password', env('DB_PASSWORD'));

        DB::purge('mysql');

        try {
            DB::disconnect('mysql');
        } catch (Throwable) {
            //
        }
    }
}

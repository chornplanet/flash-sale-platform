<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class HealthyCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:healthy-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check MySQL, database, Redis, Horizon, and Telescope readiness';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking Flash Sale Platform readiness...');

        $checks = [
            $this->checkMysqlAndDatabase(),
            $this->checkRedis(),
            $this->checkHorizon(),
            $this->checkTelescope(),
        ];

        $this->newLine();

        if (in_array(false, $checks, true)) {
            $this->error('System readiness check failed.');
            $this->displayUrls();

            return self::FAILURE;
        }

        $this->displayUrls();

        return self::SUCCESS;
    }

    private function checkMysqlAndDatabase(): bool
    {
        $database = config('database.connections.mysql.database');

        try {
            DB::connection('mysql')->select('SELECT 1');

            if (! Schema::connection('mysql')->hasTable('migrations')) {
                return $this->failCheck('MySQL/database', "Connected to '{$database}', but migrations table is missing. Run php artisan app:install.");
            }

            return $this->pass('MySQL/database', "Connected to '{$database}' and migrations table exists.");
        } catch (Throwable $exception) {
            return $this->failCheck('MySQL/database', $exception->getMessage());
        }
    }

    private function checkRedis(): bool
    {
        try {
            $response = Redis::ping();

            if ($response === true || (string) $response === 'PONG') {
                return $this->pass('Redis', 'Connection returned PONG.');
            }

            return $this->failCheck('Redis', 'Ping did not return PONG.');
        } catch (Throwable $exception) {
            return $this->failCheck('Redis', $exception->getMessage());
        }
    }

    private function checkHorizon(): bool
    {
        foreach (['pcntl', 'posix'] as $extension) {
            if (! extension_loaded($extension)) {
                return $this->failCheck('Horizon', "Missing PHP extension [{$extension}]. Run this check inside the Docker app container.");
            }
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            if ($masters === []) {
                return $this->failCheck('Horizon', 'No active Horizon master supervisor found. Start the queue service with docker compose up -d queue.');
            }

            $paused = collect($masters)
                ->filter(fn ($master) => $master->status === 'paused')
                ->pluck('name')
                ->all();

            if ($paused !== []) {
                return $this->failCheck('Horizon', 'Paused supervisors: '.implode(', ', $paused));
            }

            return $this->pass('Horizon', count($masters).' master supervisor(s) running.');
        } catch (Throwable $exception) {
            return $this->failCheck('Horizon', $exception->getMessage());
        }
    }

    private function checkTelescope(): bool
    {
        if (! config('telescope.enabled')) {
            return $this->failCheck('Telescope', 'TELESCOPE_ENABLED is false.');
        }

        if (! Route::getRoutes()->getByName('telescope')) {
            return $this->failCheck('Telescope', 'Telescope routes are not registered.');
        }

        $connection = config('telescope.storage.database.connection') ?: config('database.default');

        try {
            $schema = Schema::connection($connection);

            foreach (['telescope_entries', 'telescope_entries_tags', 'telescope_monitoring'] as $table) {
                if (! $schema->hasTable($table)) {
                    return $this->failCheck('Telescope', "Missing table [{$table}]. Run migrations.");
                }
            }

            return $this->pass('Telescope', 'Enabled, routes registered, and storage tables exist.');
        } catch (Throwable $exception) {
            return $this->failCheck('Telescope', $exception->getMessage());
        }
    }

    private function pass(string $name, string $message): bool
    {
        $this->components->info("{$name}: {$message}");

        return true;
    }

    private function failCheck(string $name, string $message): bool
    {
        $this->components->error("{$name}: {$message}");

        return false;
    }

    private function displayUrls(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->components->info('System is ready');
        $this->components->info('APP URL: '.$appUrl);
        $this->components->info('Horizon URL: '.$appUrl.'/'.trim((string) config('horizon.path', 'horizon'), '/'));
        $this->components->info('Telescope URL: '.$appUrl.'/'.trim((string) config('telescope.path', 'telescope'), '/'));
    }
}

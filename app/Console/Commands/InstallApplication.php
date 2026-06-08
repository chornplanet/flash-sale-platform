<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class InstallApplication extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Create database and run migrations';

    public function handle(): int
    {
        $this->info('Checking application key...');

        $keyResult = $this->ensureApplicationKey();

        if ($keyResult !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Creating database...');

        $databaseResult = $this->call('db:create');

        if ($databaseResult !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Running migrations...');
        DB::purge('mysql');
        DB::reconnect('mysql');

        $migrationResult = $this->call('migrate', [
            '--force' => true,
        ]);

        if ($migrationResult !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Checking Redis...');
        $redisResult = $this->call('redis:check');

        if ($redisResult !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Checking Horizon runtime...');
        $horizonResult = $this->call('horizon:check-runtime');

        if ($horizonResult !== self::SUCCESS) {
            $this->warn('Application setup can continue, but Horizon must be run from a Linux/Docker PHP runtime.');
        }

        $this->info('Application installed successfully.');

        return self::SUCCESS;
    }

    private function ensureApplicationKey(): int
    {
        if (filled(config('app.key'))) {
            $this->info('Application key is already configured.');

            return self::SUCCESS;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        $environmentPath = base_path('docker/environment/app.env');

        if (! file_exists($environmentPath)) {
            $this->error("Environment file not found: {$environmentPath}");

            return self::FAILURE;
        }

        $contents = file_get_contents($environmentPath);

        if (preg_match('/^APP_KEY=.*$/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $contents);
        } else {
            $contents .= PHP_EOL."APP_KEY={$key}".PHP_EOL;
        }

        file_put_contents($environmentPath, $contents);
        putenv("APP_KEY={$key}");
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
        Config::set('app.key', $key);

        $this->info('Generated application key in docker/environment/app.env.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckHorizonRuntime extends Command
{
    protected $signature = 'horizon:check-runtime';

    protected $description = 'Verify that the current PHP runtime can run Laravel Horizon';

    public function handle(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->error('Horizon cannot run with native Windows PHP because the pcntl extension is unavailable.');
            $this->line('Run Horizon from Docker instead: docker compose up -d queue');

            return self::FAILURE;
        }

        foreach (['pcntl', 'posix'] as $extension) {
            if (! extension_loaded($extension)) {
                $this->error(sprintf('The [%s] PHP extension is not loaded.', $extension));
                $this->line('Rebuild the Docker PHP image: docker compose build --no-cache app queue');

                return self::FAILURE;
            }
        }

        $this->info('Horizon runtime check passed.');

        return self::SUCCESS;
    }
}

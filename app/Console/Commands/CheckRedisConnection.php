<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CheckRedisConnection extends Command
{
    protected $signature = 'redis:check';

    protected $description = 'Verify Redis connection';

    public function handle(): int
    {
        $client = config('database.redis.client');

        if (! in_array($client, ['phpredis', 'predis'], true)) {
            $this->error(sprintf(
                'Unsupported Redis client [%s]. Use REDIS_CLIENT=phpredis or REDIS_CLIENT=predis.',
                $client
            ));

            return self::FAILURE;
        }

        if ($client === 'phpredis' && ! extension_loaded('redis')) {
            $this->error('The phpredis extension is not loaded. Install/enable it or use REDIS_CLIENT=predis.');

            return self::FAILURE;
        }

        if ($client === 'predis' && ! class_exists(\Predis\Client::class)) {
            $this->error('The predis/predis package is not installed. Run composer require predis/predis or use REDIS_CLIENT=phpredis.');

            return self::FAILURE;
        }

        try {
            $response = Redis::ping();

            if ($response === true || (string) $response === 'PONG') {
                $this->info('Redis connection successful.');

                return self::SUCCESS;
            }

            $this->error('Redis connection failed.');

            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->error('Redis connection failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

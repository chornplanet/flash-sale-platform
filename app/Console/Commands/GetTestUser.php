<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GetTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-test-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print the first user login details for testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = User::query()
            ->oldest('id')
            ->value('email');

        if (! $email) {
            $this->error('No users found. Seed the database before requesting a test user.');

            return self::FAILURE;
        }

        $this->line("email: {$email}");
        $this->line('password: [Request from admin]');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'school:make-admin {email : The email address of an existing account}';

    protected $description = 'Grant administrator access to an existing school account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('No account was found with that email address.');

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->components->info("{$user->email} already has administrator access.");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->components->info("Administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }
}

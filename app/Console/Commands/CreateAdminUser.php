<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAdminUser extends Command
{
    protected $signature = 'school:create-admin {username} {email}';

    protected $description = 'Create an administrator account using a hidden password prompt';

    public function handle(): int
    {
        $username = strtolower($this->argument('username'));
        $email = strtolower($this->argument('email'));

        $validator = Validator::make(
            ['username' => $username, 'email' => $email],
            [
                'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            ]
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Admin password (minimum 8 characters)');

        if (! is_string($password) || strlen($password) < 8) {
            $this->components->error('The password must be at least 8 characters long.');

            return self::FAILURE;
        }

        $user = new User([
            'name' => 'School Administrator',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->components->info("Administrator account {$user->username} created successfully.");

        return self::SUCCESS;
    }
}

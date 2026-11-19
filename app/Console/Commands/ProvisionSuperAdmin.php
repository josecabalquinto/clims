<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProvisionSuperAdmin extends Command
{
    use ProfileValidationRules;

    protected $signature = 'clims:provision-admin';

    protected $description = 'Interactively provision the first active Admin without default credentials';

    public function handle(ActivityLogger $logger): int
    {
        if (User::where('role', UserRole::Admin)->where('is_active', true)->exists()) {
            $this->error('An active Admin already exists.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter credentials securely.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Name'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($data, [
            ...$this->profileRules(),
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $logger): void {
            $administrators = User::where('role', UserRole::Admin)->orderBy('id')->lockForUpdate()->get();
            if ($administrators->where('is_active', true)->isNotEmpty()) {
                throw ValidationException::withMessages(['email' => 'An active Admin already exists.']);
            }

            $user = new User;
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            $user->role = UserRole::Admin;
            $user->is_active = true;
            $user->save();

            $logger->record(null, 'user.provisioned', $user, [], [
                'name' => $user->name, 'email' => $user->email,
                'role' => $user->role->value, 'is_active' => $user->is_active,
            ]);
        }, attempts: 3);

        $this->info('Admin created. Sign in using the credentials you entered.');

        return self::SUCCESS;
    }
}

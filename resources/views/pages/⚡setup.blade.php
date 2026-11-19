<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\InventoryReferenceSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth'), Title('Set up CLIMS')] class extends Component {
    public string $name = '';

    public string $email = '';

    public function boot(): void
    {
        abort_unless(config('nativephp-internal.running') && User::query()->doesntExist(), 404);
    }

    public function save(
        string $password,
        string $passwordConfirmation,
        ActivityLogger $logger,
        InventoryReferenceSeeder $inventoryReferenceSeeder,
    ): void
    {
        $validated = Validator::make([
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        try {
            $user = DB::transaction(function () use ($validated, $logger, $inventoryReferenceSeeder): User {
                if (User::query()->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'email' => __('CLIMS has already been set up. Sign in with an existing account.'),
                    ]);
                }

                $user = new User;
                $user->name = $validated['name'];
                $user->email = $validated['email'];
                $user->password = $validated['password'];
                $user->role = UserRole::Admin;
                $user->is_active = true;
                $user->save();

                $inventoryReferenceSeeder->run();

                $logger->record(null, 'user.provisioned', $user, [], [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'is_active' => $user->is_active,
                ]);

                return $user;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('CLIMS has already been set up. Sign in with an existing account.'),
            ]);
        }

        Auth::login($user);
        session()->regenerate();
        session()->flash('success', __('Administrator account created. CLIMS is ready to use.'));
        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-7">
    <div>
        <p class="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-brand-deep">
            <flux:icon.shield-check class="size-4" />
            {{ __('First-time setup') }}
        </p>
        <h1 class="text-[32px] leading-tight font-bold tracking-tight text-brand-deep">{{ __('Create the administrator') }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('This account will manage equipment, borrowing records, and other authorized users on this computer.') }}</p>
    </div>

    <form
        x-data
        method="POST"
        x-on:submit.prevent="$wire.save($refs.password.value, $refs.passwordConfirmation.value)"
        class="flex flex-col gap-5"
    >
        @csrf

        <flux:input wire:model="name" :label="__('Administrator name')" icon="user" required autofocus autocomplete="name" maxlength="255" />
        <flux:input wire:model="email" :label="__('Email address')" type="email" icon="envelope" required autocomplete="email" maxlength="255" />
        <flux:input x-ref="password" :label="__('Password')" type="password" icon="lock-closed" required autocomplete="new-password" viewable />
        <flux:error name="password" />
        <flux:input x-ref="passwordConfirmation" :label="__('Confirm password')" type="password" icon="lock-closed" required autocomplete="new-password" viewable />
        <flux:error name="password_confirmation" />

        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.') }}</p>

        <flux:button variant="primary" type="submit" class="mt-1 w-full" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ __('Finish setup') }}</span>
            <span wire:loading wire:target="save">{{ __('Creating administrator…') }}</span>
        </flux:button>
    </form>
</div>

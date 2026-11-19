<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-8">
        <div>
            <h1 class="text-[36px] leading-tight font-bold tracking-tight text-brand-deep">{{ __('Login') }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Welcome back. Sign in to your laboratory account.') }}</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autofocus
                autocomplete="email"
                placeholder="you@school.edu"
            />

            <div class="flex flex-col gap-3">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    icon="lock-closed"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="self-end text-xs font-medium" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="mt-2 w-full" data-test="login-button">
                {{ __('Log in') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>

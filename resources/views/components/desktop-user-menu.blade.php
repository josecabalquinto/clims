<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" class="h-auto! gap-3! px-1! py-1.5!" data-test="sidebar-menu-button" :aria-label="__('Open account menu')">
        <span class="hidden text-end sm:block">
            <span class="block text-xs font-semibold text-brand-deep">{{ auth()->user()->name }}</span>
            <span class="mt-0.5 block text-[10px] text-zinc-500 dark:text-zinc-400">{{ __('Admin') }}</span>
        </span>
        <span class="flex size-10 items-center justify-center rounded-full bg-[#087f80] text-sm font-semibold text-white">
            {{ auth()->user()->initials() }}
        </span>
    </flux:button>

    <flux:menu>
        <div class="px-2 py-2">
            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>{{ __('Settings') }}</flux:menu.item>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>

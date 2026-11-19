<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="clims-app min-h-screen bg-canvas font-sans text-ink antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:p-3 focus:text-brand-deep">{{ __('Skip to content') }}</a>

        <flux:sidebar sticky collapsible="mobile" class="clims-sidebar">
            <flux:sidebar.header class="mb-7 border-b border-[#064b4c]/15 px-2 pt-4 pb-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md text-[#064b4c] focus-visible:outline-2 focus-visible:outline-offset-4" wire:navigate>
                    <flux:icon.computer-desktop class="size-8" />
                    <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
                </a>
                <flux:sidebar.collapse class="text-[#064b4c]! lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav aria-label="{{ __('Main navigation') }}">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="computer-desktop" :href="route('equipment.index')" :current="request()->routeIs('equipment.*')" wire:navigate>
                    {{ __('Equipment') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="clipboard-document-list" :href="route('borrowing.index')" :current="request()->routeIs('borrowing.*')" wire:navigate>
                    {{ __('Borrow Log') }}
                </flux:sidebar.item>
                @can('viewAny', \App\Models\ActivityLog::class)
                    <flux:sidebar.item icon="document-text" :href="route('activity-logs.index')" :current="request()->routeIs('activity-logs.*')" wire:navigate>
                        {{ __('View Log Details') }}
                    </flux:sidebar.item>
                @endcan
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*', 'security.*', 'appearance.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <div class="mt-8 border-t border-[#064b4c]/15"></div>
            <flux:spacer />

            <form method="POST" action="{{ route('logout') }}" class="px-4 pb-6">
                @csrf
                <button type="submit" class="flex min-h-10 w-full items-center justify-center gap-2 rounded-full border border-[#064b4c]/60 px-4 py-2 text-sm font-semibold text-[#064b4c] hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#064b4c]">
                    <flux:icon.arrow-right-start-on-rectangle class="size-4" />
                    {{ __('Log out') }}
                </button>
            </form>
        </flux:sidebar>

        <flux:header class="clims-header">
            <flux:sidebar.toggle class="text-brand-deep! lg:hidden" icon="bars-2" :aria-label="__('Open navigation')" />
            <flux:spacer />
            <x-desktop-user-menu />
        </flux:header>

        {{ $slot }}

        <x-notifications />

        @fluxScripts
    </body>
</html>

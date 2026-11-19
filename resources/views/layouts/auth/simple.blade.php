<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="clims-auth min-h-screen bg-canvas font-sans text-ink antialiased">
        <main class="flex min-h-svh items-center justify-center p-4 sm:p-8 lg:p-12">
            <div class="clims-auth-card grid w-full max-w-[920px] overflow-hidden rounded-[24px] bg-surface md:min-h-[590px] md:grid-cols-2">
                <aside class="clims-auth-panel flex flex-col gap-8 p-7 sm:p-10 md:p-12" aria-label="{{ __('About CLIMS') }}">
                    <a href="{{ route('home') }}" class="flex w-fit items-center gap-3 rounded-lg text-[#064b4c] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#064b4c]" wire:navigate>
                        <flux:icon.computer-desktop class="size-8" />
                        <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
                    </a>

                    <div class="flex flex-1 flex-col items-center justify-center gap-6 py-2 text-center md:py-4">
                        <div class="flex size-32 shrink-0 items-center justify-center rounded-full bg-white p-3 sm:size-44 md:size-48 md:p-4">
                            <img
                                src="{{ asset('assets/logo/school-logo.png') }}"
                                alt="{{ __('Sagay National High School logo') }}"
                                width="2000"
                                height="2000"
                                class="size-full object-contain"
                                fetchpriority="high"
                            />
                        </div>
                        <p class="max-w-64 text-base font-medium leading-relaxed text-[#064b4c]">{{ __('Computer Laboratory Inventory Management System') }}</p>
                    </div>

                    <div class="text-center text-sm leading-relaxed text-[#064b4c]">
                        <p class="font-semibold">{{ __('Need an account?') }}</p>
                        <p class="mt-1">{{ __('Contact the Admin for access.') }}</p>
                    </div>
                </aside>

                <div class="flex items-center px-7 py-10 sm:px-10 md:px-12 md:py-14">
                    <div class="w-full">{{ $slot }}</div>
                </div>
            </div>
        </main>

        <x-notifications />

        @fluxScripts
    </body>
</html>

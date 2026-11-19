@props(['title', 'description'])

<section class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1" class="text-brand-deep!">{{ $title }}</flux:heading>
        <flux:text class="mt-2">{{ $description }}</flux:text>
    </div>

    {{ $slot }}

    <div class="rounded-[18px] border border-line bg-surface p-6">
        <flux:heading level="2">{{ __('Coming soon') }}</flux:heading>
        <flux:text class="mt-2">{{ __('This section is being prepared for laboratory operations.') }}</flux:text>
    </div>
</section>

@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-3 text-start">
    <h1 class="text-3xl leading-tight font-bold tracking-tight text-brand-deep">{{ $title }}</h1>
    <flux:subheading class="text-sm! leading-relaxed!">{{ $description }}</flux:subheading>
</div>

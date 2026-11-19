@props(['active' => 'equipment'])

<nav class="eq-section-nav" aria-label="{{ __('Equipment management') }}">
    <a href="{{ route('equipment.index') }}" @if ($active === 'equipment') aria-current="page" @endif wire:navigate>
        <flux:icon.computer-desktop class="size-4" />{{ __('Equipment') }}
    </a>
    <a href="{{ route('equipment.categories') }}" aria-label="{{ __('Equipment Categories') }}" @if ($active === 'categories') aria-current="page" @endif wire:navigate>
        <flux:icon.tag class="size-4" />{{ __('Categories') }}
    </a>
</nav>

@props(['story', 'size' => 'md'])

@php
    $colors = [
        'indigo' => 'from-indigo-200 to-violet-300',
        'rose' => 'from-rose-200 to-orange-200',
        'amber' => 'from-amber-200 to-lime-200',
        'sky' => 'from-sky-200 to-cyan-200',
        'emerald' => 'from-emerald-200 to-teal-200',
    ];
@endphp

<div {{ $attributes->class([
    'flex shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br',
    $colors[$story?->color] ?? $colors['sky'],
    'size-16 text-4xl' => $size === 'sm',
    'h-36 text-7xl' => $size === 'md',
]) }} aria-hidden="true">
    {{ $story?->emoji ?? '📖' }}
</div>

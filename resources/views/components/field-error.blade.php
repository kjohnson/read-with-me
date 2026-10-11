@props(['name', 'bag' => 'default'])

@error($name, $bag)
    <p {{ $attributes->class('mt-1.5 text-sm font-semibold text-rose-600') }}>{{ $message }}</p>
@enderror

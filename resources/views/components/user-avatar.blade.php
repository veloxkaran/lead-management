@props(['user', 'size' => 28])

<span
    {{ $attributes->merge(['class' => 'd-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white flex-shrink-0']) }}
    style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ round($size * 0.29, 1) }}px;"
>
    {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
</span>

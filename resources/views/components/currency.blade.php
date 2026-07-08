@props(['amount', 'decimals' => 2])

<span {{ $attributes }}>{{ \App\Support\Currency::format($amount, $decimals) }}</span>

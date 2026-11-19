{{-- Plain-text part (HTML-only mail scores as spam). {!! !!}: not HTML, so no escaping. --}}
{!! $body !!}
@if (! empty($documentNames))

Attached: {!! implode(', ', $documentNames) !!}
@endif

--
{!! $footer ?: (\App\Models\Setting::get('company_name') ?: config('app.name')) !!}
@if (! empty($unsubscribeUrl))
Unsubscribe: {!! $unsubscribeUrl !!}
@endif

{{-- Plain-text part. {!! !!} throughout: this isn't HTML, so escaping would show &amp; etc. --}}
{!! $body !!}
@if ($documents)

Attached: {!! collect($documents)->pluck('name')->implode(', ') !!}
@endif
@if ($signatureText)

{!! $signatureText !!}
@endif

--
{!! $footer ?: (\App\Models\Setting::get('company_name') ?: config('app.name')) !!}
@if ($unsubscribeUrl)
Unsubscribe: {!! $unsubscribeUrl !!}
@endif

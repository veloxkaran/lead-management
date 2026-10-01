{{-- Plain-text part. {!! !!} throughout: this isn't HTML, so escaping would show &amp; etc. --}}
{!! $body !!}
@if ($signatureText)

{!! $signatureText !!}
@endif

--
{!! $footer ?: (\App\Models\Setting::get('company_name') ?: config('app.name')) !!}
@if ($unsubscribeUrl)
Unsubscribe: {!! $unsubscribeUrl !!}
@endif

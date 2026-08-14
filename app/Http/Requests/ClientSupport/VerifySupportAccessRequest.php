<?php

namespace App\Http\Requests\ClientSupport;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verifies a Support ID + PIN for the public client self-service portal
 * (ClientSupportController). Rate-limiting mirrors Auth\LoginRequest exactly
 * — manual RateLimiter facade calls, the only rate-limiting convention this
 * app uses (no route throttle: middleware anywhere). A 4-digit PIN is only
 * 10,000 combinations, so on top of a per-IP key (mirrors login's
 * per-identifier+IP key, just longer decay), a second, coarser limiter keyed
 * on the Support ID alone catches an attempt distributed across many IPs.
 */
class VerifySupportAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'support_id' => ['required', 'string'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    /**
     * Returns the matching Lead once its Support ID + PIN both check out, or
     * throws a single generic validation error otherwise — deliberately
     * never reveals whether the Support ID or the PIN was the wrong part.
     */
    public function attempt(): Lead
    {
        $this->ensureIsNotRateLimited();

        // Support IDs are generated uppercase (LeadService::generateUniqueSupportId());
        // normalize here so a client typing it lowercase still matches.
        $lead = Lead::where('support_id', Str::upper(trim($this->string('support_id'))))->first();

        if (! $lead || ! $lead->verifySupportPin($this->string('pin'))) {
            RateLimiter::hit($this->ipThrottleKey(), 900); // 15 min
            RateLimiter::hit($this->supportIdThrottleKey(), 3600); // 1 hour

            throw ValidationException::withMessages([
                'pin' => 'That Support ID or PIN is incorrect.',
            ]);
        }

        RateLimiter::clear($this->ipThrottleKey());
        RateLimiter::clear($this->supportIdThrottleKey());

        return $lead;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->ipThrottleKey(), 5)) {
            throw $this->throttledResponse($this->ipThrottleKey());
        }

        if (RateLimiter::tooManyAttempts($this->supportIdThrottleKey(), 20)) {
            throw $this->throttledResponse($this->supportIdThrottleKey());
        }
    }

    private function throttledResponse(string $key): ValidationException
    {
        $seconds = RateLimiter::availableIn($key);

        return ValidationException::withMessages([
            'pin' => 'Too many attempts. Please try again in '.max(1, ceil($seconds / 60)).' minute(s).',
        ]);
    }

    public function ipThrottleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('support_id')).'|'.$this->ip());
    }

    public function supportIdThrottleKey(): string
    {
        return 'support-id:'.Str::transliterate(Str::lower($this->string('support_id')));
    }
}

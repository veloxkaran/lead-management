<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets throwaway "public" and "local" disks. Without this a
     * test that saves through real app code writes to — and cleans up in —
     * the real storage/app folders, which hold live uploads (a test once
     * deleted the live campaign signature image this way).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * Creates a campaign the way the composer does: preview first (the
     * store refuses one that wasn't previewed), then submit with the
     * preview's token. If the preview itself is refused, the form is
     * submitted without a token so the store's own errors come back.
     */
    protected function postCampaign(array $payload): TestResponse
    {
        $preview = $this->postJson(route('campaigns.compose-preview'), $payload);
        $token = $preview->status() === 200 ? $preview->json('token') : null;

        return $this->post(route('campaigns.store'), $token ? [...$payload, 'preview_token' => $token] : $payload);
    }
}

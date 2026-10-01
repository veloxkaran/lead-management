<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

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
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run with the real .env credentials (Twilio, Airalo, Paystack…). Any HTTP call a
        // test hasn't faked must fail loudly rather than reach a live provider.
        Http::preventStrayRequests();
    }
}

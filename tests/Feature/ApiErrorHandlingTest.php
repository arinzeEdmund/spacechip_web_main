<?php

namespace Tests\Feature;

use App\Services\AiraloService;
use App\Services\FiveSimService;
use App\Services\CryptomusService;
use App\Services\GloEsimService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    /** @test */
    public function test_twilio_service_handles_5xx_error()
    {
        Http::fake([
            '*/Accounts/*' => Http::response(['message' => 'Twilio is down'], 503),
        ]);

        $service = new TwilioService();
        $response = $service->availableCountries();

        $this->assertFalse($response['ok']);
        $this->assertEquals(503, $response['status']);
        $this->assertStringContainsString('unavailable', $response['error']);
    }

    public function test_airalo_service_handles_401_and_retries()
    {
        Http::fake([
            '*/token' => Http::response(['data' => ['access_token' => 'new_token']], 200),
            '*/packages*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['data' => [['id' => 'pkg1', 'title' => 'Package 1']]], 200),
        ]);

        $service = new AiraloService();
        $response = $service->allRegionsWithPrices();

        $this->assertNotEmpty($response);
        $this->assertEquals('Package 1', $response[0]['name']);
        
        // Should have 2 token calls + 2 package calls
        // 1st request: token call (1), package call (2) -> 401
        // 2nd request (retry): token call (3), package call (4) -> 200
        Http::assertSentCount(4);
    }

    public function test_airalo_service_handles_500_error()
    {
        Http::fake([
            '*/token' => Http::response(['data' => ['access_token' => 'token']], 200),
            '*/packages*' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $service = new AiraloService();
        $response = $service->getRawPackages();

        $this->assertFalse($response['ok']);
        $this->assertEquals(500, $response['status']);
        $this->assertStringContainsString('unavailable', $response['error']);
    }

    public function test_airalo_service_handles_connection_timeout()
    {
        Http::fake([
            '*/token' => Http::response(['data' => ['access_token' => 'token']], 200),
            '*/packages*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $service = new AiraloService();
        $response = $service->getRawPackages();

        $this->assertFalse($response['ok']);
        $this->assertEquals(500, $response['status']);
        $this->assertStringContainsString('connection error', $response['error']);
    }

    public function test_fivesim_service_handles_4xx_error()
    {
        Http::fake([
            '*/guest/countries' => Http::response(['message' => 'Bad Request'], 400),
        ]);

        $service = new FiveSimService();
        $response = $service->guestCountries();

        $this->assertFalse($response['ok']);
        $this->assertEquals(400, $response['status']);
        $this->assertEquals('Bad Request', $response['error']);
    }

    public function test_cryptomus_service_handles_5xx_error()
    {
        Http::fake([
            '*/v1/payment' => Http::response(['message' => 'Gateway Timeout'], 504),
        ]);

        $service = new CryptomusService();
        $response = $service->createInvoice(['amount' => 10, 'currency' => 'USD', 'order_id' => '123']);

        $this->assertEquals(1, $response['state']);
        $this->assertEquals(504, $response['status']);
        $this->assertStringContainsString('unavailable', $response['message']);
    }

    public function test_gloesim_service_handles_404_error()
    {
        Http::fake([
            '*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $service = new GloEsimService();
        $response = $service->popularCountries();

        // GloEsimService returns empty array or fallback on error
        $this->assertIsArray($response);
    }
}

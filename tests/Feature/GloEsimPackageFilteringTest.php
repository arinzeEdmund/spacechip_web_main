<?php

namespace Tests\Feature;

use App\Services\GloEsimService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GloEsimPackageFilteringTest extends TestCase
{
    public function test_packages_by_country_id_filters_by_package_type_even_if_api_returns_mixed(): void
    {
        Cache::flush();

        Config::set('services.gloesim.base_url', 'https://example.test/api');
        Config::set('services.gloesim.dealer_email', 'dealer@example.test');
        Config::set('services.gloesim.dealer_password', 'password');

        Http::fake([
            'https://example.test/api/developer/dealer/login' => Http::response(['token' => 'tok'], 200),
            'https://example.test/api/developer/dealer/packages/country/123*' => Http::response([
                'data' => [
                    ['id' => 1, 'package_type' => 'DATA-ONLY', 'voice' => 0, 'sms' => 0],
                    ['id' => 2, 'voice' => 30, 'sms' => 50],
                ],
            ], 200),
        ]);

        $service = app(GloEsimService::class);

        $dataOnly = $service->packagesByCountryId(123, 'DATA-ONLY');
        $this->assertCount(1, $dataOnly);
        $this->assertSame(1, data_get($dataOnly[0], 'id'));

        $dataVoiceSms = $service->packagesByCountryId(123, 'DATA-VOICE-SMS');
        $this->assertCount(1, $dataVoiceSms);
        $this->assertSame(2, data_get($dataVoiceSms[0], 'id'));
    }

    public function test_packages_by_country_id_falls_back_to_global_for_data_voice_sms_when_country_endpoint_ignores_filter(): void
    {
        Cache::flush();

        Config::set('services.gloesim.base_url', 'https://example.test/api');
        Config::set('services.gloesim.dealer_email', 'dealer@example.test');
        Config::set('services.gloesim.dealer_password', 'password');

        Http::fake([
            'https://example.test/api/developer/dealer/login' => Http::response(['token' => 'tok'], 200),
            'https://example.test/api/developer/dealer/packages/country/123*' => Http::response([
                'data' => [
                    ['id' => 1, 'package_type' => 'DATA-ONLY', 'voice' => 0, 'sms' => 0],
                ],
            ], 200),
            'https://example.test/api/developer/dealer/packages/global*' => Http::response([
                'data' => [
                    ['id' => 99, 'package_type' => 'DATA-VOICE-SMS', 'voice' => 30, 'sms' => 50, 'countries' => [['id' => '123']]],
                ],
            ], 200),
        ]);

        $service = app(GloEsimService::class);

        $dataVoiceSms = $service->packagesByCountryId(123, 'DATA-VOICE-SMS');
        $this->assertCount(1, $dataVoiceSms);
        $this->assertSame(99, data_get($dataVoiceSms[0], 'id'));
    }
}

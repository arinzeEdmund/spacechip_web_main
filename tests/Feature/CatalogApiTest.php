<?php

namespace Tests\Feature;

use App\Models\VirtualNumberProduct;
use App\Services\AiraloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $airalo = Mockery::mock(AiraloService::class);
        $airalo->shouldReceive('allCountriesWithPrices')->andReturn(
            collect(range(1, 35))->map(fn ($i) => ['id' => "c$i", 'name' => $i === 7 ? 'Nigeria' : "Country $i"])->all()
        );
        $airalo->shouldReceive('allRegionsWithPrices')->andReturn([['id' => 'europe', 'name' => 'Europe']]);
        $airalo->shouldReceive('getAssetDetails')->andReturnUsing(
            fn ($type, $id) => $id === 'missing' ? null : ['bundles' => [['id' => "$type-$id-1gb"]]]
        );
        $this->app->instance(AiraloService::class, $airalo);

        VirtualNumberProduct::create(['country_iso' => 'us', 'label' => 'Local', 'currency' => 'usd', 'monthly_amount_minor' => 499, 'active' => true]);
        VirtualNumberProduct::create(['country_iso' => 'gb', 'label' => 'Mobile', 'currency' => 'usd', 'monthly_amount_minor' => 1250, 'active' => true]);
        VirtualNumberProduct::create(['country_iso' => 'fr', 'label' => 'Off', 'currency' => 'EUR', 'monthly_amount_minor' => 100, 'active' => false]);
    }

    public function test_unpaged_index_returns_every_list(): void
    {
        $res = $this->getJson('/api/allassets')->assertOk();

        $this->assertCount(35, $res->json('countries'));
        $this->assertSame([['id' => 'europe', 'name' => 'Europe']], $res->json('regions'));
        $this->assertSame([
            [
                'id' => 2, 'product_id' => 2, 'name' => 'GB · Mobile',
                'description' => 'Monthly virtual number subscription (SMS & calls).',
                'price_formatted' => 'USD 12.50', 'flag' => '☎️', 'flag_url' => '',
                'url' => route('dashboard.virtual.country', ['country' => 'GB']),
            ],
            [
                'id' => 1, 'product_id' => 1, 'name' => 'US · Local',
                'description' => 'Monthly virtual number subscription (SMS & calls).',
                'price_formatted' => 'USD 4.99', 'flag' => '☎️', 'flag_url' => '',
                'url' => route('dashboard.virtual.country', ['country' => 'US']),
            ],
        ], $res->json('virtualNumbers'));
    }

    public function test_paged_index_paginates_searches_and_clamps(): void
    {
        $this->getJson('/api/allassets?tab=countries&page=2&per_page=30')
            ->assertOk()
            ->assertJson(['tab' => 'countries', 'page' => 2, 'per_page' => 30, 'total' => 35, 'has_more' => false])
            ->assertJsonCount(5, 'items');

        $this->getJson('/api/allassets?tab=countries&per_page=5')
            ->assertJson(['per_page' => 10, 'has_more' => true])
            ->assertJsonCount(10, 'items');

        $this->getJson('/api/allassets?tab=countries&q=NIGER')
            ->assertJson(['total' => 1, 'items' => [['id' => 'c7']]]);

        $this->getJson('/api/allassets?tab=bogus')->assertJson(['tab' => 'countries']);
        $this->getJson('/api/allassets?tab=regions')->assertJson(['total' => 1]);
        $this->getJson('/api/allassets?tab=virtual&q=gb')
            ->assertJson(['total' => 1, 'items' => [['name' => 'GB · Mobile', 'price_formatted' => 'USD 12.50']]]);
    }

    public function test_bundles_endpoint(): void
    {
        $this->getJson('/api/assets/country/ng/bundles')
            ->assertOk()
            ->assertExactJson(['type' => 'country', 'id' => 'ng', 'package_type' => 'DATA-ONLY', 'bundles' => [['id' => 'country-ng-1gb']]]);

        $this->getJson('/api/assets/country/ng/bundles?package_type=BAD')->assertStatus(422);
        $this->getJson('/api/assets/country/missing/bundles')->assertNotFound();
    }
}

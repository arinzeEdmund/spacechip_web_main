<?php

namespace Tests\Feature;

use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $over = []): SocialNumberOrder
    {
        return SocialNumberOrder::create(array_merge([
            'user_id' => User::factory()->create()->id, 'provider' => 'smspool', 'provider_order_id' => 'o'.uniqid(), 'status' => 'RECEIVED',
            'product' => 'whatsapp', 'product_name' => 'WhatsApp', 'service_code' => 'whatsapp', 'country' => 'US', 'country_name' => 'United States',
            'sms_code' => '482913', 'sms_text' => 'Your WhatsApp code is 482913',
        ], $over));
    }

    public function test_sms_content_is_encrypted_at_rest_but_readable_through_the_model(): void
    {
        $o = $this->order();
        $raw = DB::table('social_number_orders')->where('id', $o->id)->first();

        $this->assertStringNotContainsString('482913', (string) $raw->sms_code);
        $this->assertStringNotContainsString('482913', (string) $raw->sms_text);
        $this->assertSame('482913', $o->fresh()->sms_code);
        $this->assertSame('Your WhatsApp code is 482913', $o->fresh()->sms_text);
    }

    public function test_legacy_plaintext_rows_still_read_and_get_encrypted_by_the_command(): void
    {
        $o = $this->order();
        DB::table('social_number_orders')->where('id', $o->id)->update(['sms_code' => '111222', 'sms_text' => 'legacy plaintext']);
        $this->assertSame('111222', $o->fresh()->sms_code);

        $this->artisan('data:encrypt-messages')->assertSuccessful();
        $this->assertNotSame('111222', DB::table('social_number_orders')->where('id', $o->id)->value('sms_code'));
        $this->assertSame('111222', $o->fresh()->sms_code);
    }

    public function test_rental_inbox_is_encrypted_json(): void
    {
        $u = User::factory()->create();
        $r = SocialNumberRental::create(['user_id' => $u->id, 'provider' => 'smspool_rent', 'provider_order_id' => 'r1', 'status' => 'active', 'product' => 'whatsapp', 'product_name' => 'WhatsApp', 'service_code' => 'whatsapp', 'country' => 'US', 'sms_messages' => [['sender' => 'X', 'text' => 'secret 998877', 'date' => time()]]]);

        $this->assertStringNotContainsString('998877', (string) DB::table('social_number_rentals')->where('id', $r->id)->value('sms_messages'));
        $this->assertSame('secret 998877', $r->fresh()->sms_messages[0]['text']);
    }

    public function test_purge_removes_only_old_message_content(): void
    {
        $old = $this->order();
        $new = $this->order();
        DB::table('social_number_orders')->where('id', $old->id)->update(['created_at' => now()->subDays(120)]);

        $this->artisan('messages:purge')->assertSuccessful();

        $this->assertNull($old->fresh()->sms_code);
        $this->assertSame('RECEIVED', $old->fresh()->status); // the order record itself stays
        $this->assertSame('482913', $new->fresh()->sms_code);
    }

    public function test_wallet_reconcile_passes_on_a_clean_ledger_and_flags_tampering(): void
    {
        $u = User::factory()->create();
        app(\App\Services\WalletService::class)->credit($u->id, 1000, 'deposit');
        app(\App\Services\WalletService::class)->debit($u->id, 300, 'buy');
        $this->artisan('wallet:reconcile')->assertSuccessful();

        DB::table('users')->where('id', $u->id)->update(['wallet_balance_minor' => 999999]); // someone edits the DB
        $this->artisan('wallet:reconcile')->assertFailed();
        $this->assertGreaterThan(0, WalletTransaction::count());
    }

    public function test_esim_activation_data_is_encrypted_but_findable_by_iccid(): void
    {
        $u = User::factory()->create();
        $p = \App\Models\Payment::create([
            'user_id' => $u->id, 'provider' => 'paystack', 'provider_reference' => 'e1', 'status' => 'fulfilled', 'asset_type' => 'country',
            'asset_id' => 'us', 'bundle_id' => 'b', 'package_type' => 'x', 'currency' => 'USD', 'amount_minor' => 500,
            'fulfillment_payload' => ['ok' => true, 'iccid' => '8944500000000000001', 'lpa' => 'LPA:1$smdp.example$SECRETCODE123'],
        ]);

        $raw = (string) DB::table('payments')->where('id', $p->id)->value('fulfillment_payload');
        $this->assertStringNotContainsString('SECRETCODE123', $raw);
        $this->assertStringNotContainsString('8944500000000000001', $raw);
        $this->assertSame('LPA:1$smdp.example$SECRETCODE123', $p->fresh()->fulfillment_payload['lpa']);
        $this->assertSame($p->id, \App\Models\Payment::where('iccid_index', \App\Models\Payment::iccidIndex('8944500000000000001'))->value('id'));
    }
}

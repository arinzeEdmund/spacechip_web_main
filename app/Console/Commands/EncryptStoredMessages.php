<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\VirtualNumberMessage;
use Illuminate\Console\Command;

/** One-off: encrypts SMS content that was stored before encryption-at-rest was switched on. Safe to re-run. */
class EncryptStoredMessages extends Command
{
    protected $signature = 'data:encrypt-messages';

    protected $description = 'Encrypt existing SMS / message content at rest';

    public function handle(): int
    {
        $n = 0;
        SocialNumberOrder::query()->where(fn ($q) => $q->whereNotNull('sms_code')->orWhereNotNull('sms_text'))->chunkById(200, function ($rows) use (&$n) {
            foreach ($rows as $r) {
                $r->sms_code = $r->sms_code;
                $r->sms_text = $r->sms_text;
                $r->save();
                $n++;
            }
        });
        SocialNumberRental::query()->whereNotNull('sms_messages')->chunkById(200, function ($rows) use (&$n) {
            foreach ($rows as $r) {
                $r->sms_messages = $r->sms_messages;
                $r->save();
                $n++;
            }
        });
        VirtualNumberMessage::query()->chunkById(200, function ($rows) use (&$n) {
            foreach ($rows as $r) {
                $r->body = $r->body;
                $r->raw = $r->raw;
                $r->save();
                $n++;
            }
        });
        // eSIM activation data: encrypts fulfillment_payload and fills the ICCID blind index.
        Payment::query()->whereNotNull('fulfillment_payload')->chunkById(200, function ($rows) use (&$n) {
            foreach ($rows as $r) {
                $r->fulfillment_payload = $r->fulfillment_payload;
                $r->iccid_index = null; // force the saving hook to recompute it
                $r->save();
                $n++;
            }
        });
        $this->info("Re-saved {$n} rows with encryption on.");

        return self::SUCCESS;
    }
}

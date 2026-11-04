<?php

namespace App\Console\Commands;

use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\VirtualNumberMessage;
use Illuminate\Console\Command;

/**
 * Data minimisation: verification SMS are only needed briefly. Removes old message content (never the order or
 * payment record). Windows come from config/retention.php.
 */
class PurgeOldMessages extends Command
{
    protected $signature = 'messages:purge';

    protected $description = 'Delete SMS content older than the retention windows';

    public function handle(): int
    {
        $otpDays = max(7, (int) config('retention.social_otp_days', 90));
        $rentalDays = max(7, (int) config('retention.rental_sms_days', 90));
        $virtualDays = max(30, (int) config('retention.virtual_message_days', 365));

        $orders = SocialNumberOrder::query()->where('created_at', '<', now()->subDays($otpDays))
            ->where(fn ($q) => $q->whereNotNull('sms_code')->orWhereNotNull('sms_text'))
            ->update(['sms_code' => null, 'sms_text' => null]);

        $rentals = 0;
        $cut = now()->subDays($rentalDays)->timestamp;
        SocialNumberRental::query()->whereNotNull('sms_messages')->chunkById(200, function ($rows) use (&$rentals, $cut) {
            foreach ($rows as $r) {
                $all = (array) $r->sms_messages;
                $keep = array_values(array_filter($all, fn ($m) => (int) ($m['date'] ?? time()) >= $cut));
                if (count($keep) !== count($all)) {
                    $r->sms_messages = $keep;
                    $r->save();
                    $rentals++;
                }
            }
        });

        $virtual = VirtualNumberMessage::query()->where('created_at', '<', now()->subDays($virtualDays))->delete();

        $this->info("Purged: {$orders} OTP orders, {$rentals} rentals trimmed, {$virtual} virtual-number messages.");

        return self::SUCCESS;
    }
}

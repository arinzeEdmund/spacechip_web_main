<?php

namespace App\Mail;

use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One branded template for every transactional notice (eSIM delivered, wallet funded,
 * number activated, inbound SMS/voicemail, low-data alerts).
 *
 * Empty values are dropped by the view, so callers can pass optional fields as-is.
 */
class NoticeMail extends Mailable
{
    /**
     * @param  array<string, ?string>  $rows  label => value detail lines
     * @param  array<string, ?string>  $chips  label => value pills shown above the rows
     * @param  array{label: string, url: string}|null  $action  primary button
     * @param  array<string, ?string>  $links  label => url secondary links
     * @param  array<int, array{data: string, name: string, mime: string}>  $files
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $kicker,
        public readonly ?string $heading = null,
        public readonly array $rows = [],
        public readonly array $chips = [],
        public readonly ?string $quote = null,
        public readonly ?string $note = null,
        public readonly ?array $action = null,
        public readonly array $links = [],
        public readonly ?string $imageDataUrl = null,
        public readonly ?string $footnote = null,
        public readonly array $files = [],
    ) {}

    public static function numberActivated(VirtualNumberSubscription $sub, ?VirtualNumberProduct $product): self
    {
        return new self(
            subjectLine: 'Your Spacechip virtual number is active',
            kicker: 'Virtual number activated',
            heading: 'Your number is ready',
            rows: [
                'Number' => (string) $sub->phone_number,
                'Plan' => (string) ($product?->label ?? 'Virtual Number'),
                'Next renewal' => $sub->current_period_end?->toDayDateTimeString(),
            ],
            footnote: 'You can receive SMS/MMS and voicemail in your dashboard: Virtual Numbers → My Numbers → Inbox.',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notice');
    }

    public function attachments(): array
    {
        return array_map(
            fn (array $f) => Attachment::fromData(fn () => $f['data'], $f['name'])->withMime($f['mime']),
            $this->files,
        );
    }
}

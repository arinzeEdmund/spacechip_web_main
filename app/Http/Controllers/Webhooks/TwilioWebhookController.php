<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberSubscription;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Twilio callbacks for virtual numbers: inbound SMS/MMS, inbound calls (straight to voicemail)
 * and finished recordings. Every request must carry a valid X-Twilio-Signature.
 * Messages are stored for the dashboard inbox and forwarded to the subscriber's email/phone.
 */
class TwilioWebhookController extends Controller
{
    private const EMPTY_TWIML = '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';

    public function __construct(private TwilioService $twilio) {}

    public function sms(Request $request): Response
    {
        $this->verifySignature($request);

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');
        $body = (string) $request->input('Body', '');
        $sid = (string) $request->input('MessageSid', '');
        $numMedia = (int) $request->input('NumMedia', 0);
        $kind = $numMedia > 0 ? 'MMS' : 'SMS';

        $media = [];
        for ($i = 0; $i < $numMedia; $i++) {
            $url = trim((string) $request->input("MediaUrl{$i}", ''));
            if ($url !== '') {
                $media[] = array_filter(['url' => $url, 'content_type' => trim((string) $request->input("MediaContentType{$i}", ''))], fn ($v) => $v !== '');
            }
        }

        $sub = VirtualNumberSubscription::query()->where('phone_number', $to)->first();
        if (! $sub) {
            return $this->twiml();
        }

        VirtualNumberMessage::create([
            'virtual_number_subscription_id' => $sub->id,
            'direction' => 'inbound',
            'message_type' => strtolower($kind),
            'from' => $from,
            'to' => $to,
            'body' => $body,
            'twilio_message_sid' => $sid ?: null,
            'media' => $media ?: null,
            'raw' => $request->all(),
        ]);

        $this->forwardByEmail($sub, new NoticeMail(
            subjectLine: 'New message to your Spacechip number',
            kicker: "Incoming {$kind}",
            rows: ['To' => $to, 'From' => $from],
            quote: $body,
            note: $numMedia > 0 ? "MMS media: {$numMedia} attachment(s). View in your Spacechip dashboard inbox." : null,
            footnote: 'Open Dashboard → Virtual Numbers → My Numbers → Inbox to reply.',
        ));

        $text = $body !== ''
            ? "Spacechip SMS from {$from}: {$body}"
            : "Spacechip {$kind} received from {$from}. View in dashboard inbox.";
        $this->forwardBySms($sub, mb_substr($text, 0, 1500));

        return $this->twiml();
    }

    /** Inbound call: reject unknown numbers, otherwise record a voicemail (≤ 2 min). */
    public function voice(Request $request): Response
    {
        $this->verifySignature($request);

        if (! VirtualNumberSubscription::query()->where('phone_number', (string) $request->input('To', ''))->exists()) {
            return $this->twiml('<Reject/>');
        }

        return $this->twiml(
            '<Say>Please leave a message after the tone.</Say>'
            .'<Record maxLength="120" playBeep="true" recordingStatusCallback="'.e(url('/api/twilio/voice/recording')).'" recordingStatusCallbackMethod="POST" />'
            .'<Say>Goodbye.</Say>'
        );
    }

    /** Recording finished: store it (idempotent per RecordingSid) and notify the subscriber. */
    public function voiceRecording(Request $request): Response
    {
        $this->verifySignature($request);

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');
        $recordingSid = (string) $request->input('RecordingSid', '');
        $duration = (int) $request->input('RecordingDuration', 0);

        $sub = VirtualNumberSubscription::query()->where('phone_number', $to)->first();
        if (! $sub || $recordingSid === '') {
            return $this->twiml();
        }

        VirtualNumberMessage::updateOrCreate(['twilio_recording_sid' => $recordingSid], [
            'virtual_number_subscription_id' => $sub->id,
            'direction' => 'inbound',
            'message_type' => 'voicemail',
            'from' => $from,
            'to' => $to,
            'twilio_call_sid' => ((string) $request->input('CallSid', '')) ?: null,
            'recording_url' => ((string) $request->input('RecordingUrl', '')) ?: null,
            'recording_duration' => $duration > 0 ? $duration : null,
            'raw' => $request->all(),
        ]);

        $this->forwardByEmail($sub, new NoticeMail(
            subjectLine: 'New voicemail on your Spacechip number',
            kicker: 'Voicemail received',
            rows: ['To' => $to, 'From' => $from, 'Duration' => $duration > 0 ? "{$duration}s" : null],
            footnote: 'Open Dashboard → Virtual Numbers → My Numbers → Inbox to play the voicemail.',
        ));

        $this->forwardBySms($sub, "Spacechip voicemail from {$from} to {$to}".($duration > 0 ? " ({$duration}s)" : '').'. Check dashboard inbox.');

        return $this->twiml();
    }

    private function verifySignature(Request $request): void
    {
        $params = $request->post();

        if (! $this->twilio->validateSignature($request->fullUrl(), is_array($params) ? $params : [], (string) $request->header('X-Twilio-Signature', ''))) {
            abort(403);
        }
    }

    private function forwardByEmail(VirtualNumberSubscription $sub, NoticeMail $mail): void
    {
        $email = trim((string) ($sub->forward_to_email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable) {
        }
    }

    /** Relay a short text from the virtual number to the subscriber's own phone (E.164). */
    private function forwardBySms(VirtualNumberSubscription $sub, string $text): void
    {
        $phone = trim((string) ($sub->forward_to_phone ?? ''));
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            return;
        }

        try {
            $this->twilio->sendMessage((string) $sub->phone_number, $phone, $text, []);
        } catch (Throwable) {
        }
    }

    private function twiml(string $verbs = ''): Response
    {
        return response($verbs === '' ? self::EMPTY_TWIML : '<?xml version="1.0" encoding="UTF-8"?><Response>'.$verbs.'</Response>', 200)
            ->header('Content-Type', 'text/xml');
    }
}

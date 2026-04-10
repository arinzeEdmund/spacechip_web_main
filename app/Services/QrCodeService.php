<?php

namespace App\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function svgString(string $payload, int $size = 240): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        $size = max(120, min(600, $size));

        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }

    public function svgDataUrl(string $payload, int $size = 240): string
    {
        $svg = $this->svgString($payload, $size);
        if ($svg === '') {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function esimQrPayload(?string $activationCode, ?string $smdpAddress = null): string
    {
        $activationCode = is_string($activationCode) ? trim($activationCode) : '';
        $smdpAddress = is_string($smdpAddress) ? trim($smdpAddress) : '';

        if ($activationCode === '') {
            return '';
        }

        if (str_starts_with($activationCode, 'LPA:')) {
            return $activationCode;
        }

        if ($smdpAddress !== '') {
            return 'LPA:1$'.$smdpAddress.'$'.$activationCode;
        }

        return $activationCode;
    }
}


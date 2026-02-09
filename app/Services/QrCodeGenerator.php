<?php

namespace App\Services;

class QrCodeGenerator
{
    /**
     * Generate a QR code image as a base64 data URI from a string payload.
     * Returns null if generation fails or endroid/qr-code package is unavailable.
     */
    public function toDataUri(string $payload, int $size = 200): ?string
    {
        if (! class_exists(\Endroid\QrCode\QrCode::class)) {
            return null;
        }

        try {
            $qrCode = \Endroid\QrCode\QrCode::create($payload)->setSize($size);
            $writer = new \Endroid\QrCode\Writer\PngWriter;
            $result = $writer->write($qrCode);

            return $result->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }
}

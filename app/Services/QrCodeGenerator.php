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
            $writers = [];
            if (class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
                $writers[] = new \Endroid\QrCode\Writer\SvgWriter;
            }
            if (class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
                $writers[] = new \Endroid\QrCode\Writer\PngWriter;
            }

            foreach ($writers as $writer) {
                try {
                    return $writer->write($qrCode)->getDataUri();
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}

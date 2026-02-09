<?php

namespace App\Services\Gateways;

enum GatewayCapability: string
{
    case QR = 'qr';

    case REDIRECT = 'redirect';

    case API_ONLY = 'api_only';
}

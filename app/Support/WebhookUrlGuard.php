<?php

namespace App\Support;

class WebhookUrlGuard
{
    /**
     * @return list<string>
     */
    public static function blockedHostnames(): array
    {
        return [
            'localhost',
            'localhost.localdomain',
            'metadata.google.internal',
        ];
    }

    public static function isAllowed(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if (in_array($host, self::blockedHostnames(), true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        $resolvedIps = self::resolveHostIps($host);

        if ($resolvedIps === []) {
            return self::isPublicHostname($host);
        }

        foreach ($resolvedIps as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicHostname(string $host): bool
    {
        if (in_array($host, self::blockedHostnames(), true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host);
    }

    /**
     * @return list<string>
     */
    private static function resolveHostIps(string $host): array
    {
        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === [] && filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips[] = $host;
        }

        return array_values(array_unique($ips));
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}

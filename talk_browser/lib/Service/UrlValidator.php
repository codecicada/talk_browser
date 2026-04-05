<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Service;

/**
 * Validates that a URL is safe for server-side fetching, preventing SSRF attacks.
 *
 * Checks that the URL's host resolves only to public IP addresses — blocking
 * loopback, private RFC1918 ranges, link-local, and other reserved ranges.
 */
class UrlValidator {

    /**
     * Returns true if the URL is safe to fetch externally (public host, valid scheme).
     * Returns false if the URL targets a private/reserved/loopback address, or if
     * DNS resolution fails.
     */
    public function validateExternalUrl(string $url): bool {
        $parsed = parse_url($url);

        if (!isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Strip IPv6 brackets: http://[::1]/ -> ::1
        $host = $parsed['host'];
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // Task 1.2: If the host is already a raw IP address, validate it directly.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        // Task 1.3: Resolve hostname via DNS for A (IPv4) and AAAA (IPv6) records.
        $ips = $this->resolveHostname($host);

        // Task 1.5: DNS failure — block the request.
        if (empty($ips)) {
            return false;
        }

        // Task 1.4: Reject if ANY resolved IP is private/reserved.
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if the given IP address is a public (non-private, non-reserved,
     * non-loopback) address.
     *
     * Uses PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE which covers:
     *   - IPv4 loopback:   127.0.0.0/8
     *   - IPv4 private:    10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
     *   - IPv4 link-local: 169.254.0.0/16
     *   - IPv4 reserved:   0.0.0.0/8, 240.0.0.0/4 (NO_RES_RANGE)
     *   - IPv6 loopback:   ::1
     *   - IPv6 link-local: fe80::/10
     *   - IPv6 ULA:        fc00::/7
     */
    private function isPublicIp(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Resolves a hostname to all of its IP addresses (A + AAAA records).
     * Falls back to gethostbyname() for IPv4 if dns_get_record() is unavailable.
     *
     * Returns an empty array on resolution failure.
     *
     * @return string[]
     */
    private function resolveHostname(string $host): array {
        $ips = [];

        // Primary: dns_get_record() for both A and AAAA records.
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records) && !empty($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $ips[] = $record['ip'];   // A record (IPv4)
                    } elseif (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6']; // AAAA record (IPv6)
                    }
                }
                return $ips;
            }
        }

        // Fallback: gethostbyname() (IPv4 only).
        // If resolution fails it returns the hostname unchanged — treat as failure.
        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            return []; // Resolution failed
        }

        return [$resolved];
    }
}

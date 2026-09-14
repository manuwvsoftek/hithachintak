<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * India-only access restriction, backed by a bundled IP-range dataset
 * (App\Libraries\Security\CidrCountryList) rather than a live geo-IP
 * lookup service — no external API call per request, but the data needs
 * periodic refreshing as IP allocations change (see the header comment
 * in app/Libraries/Security/data/india-ipv4.txt for source/fetch date).
 *
 * This is opt-in and OFF by default, on purpose: it's a real,
 * deliberately blunt instrument (a VPN/transit route can put a
 * legitimate India-based user outside these ranges, and a stale dataset
 * can create false negatives) — flip it on in .env only once you've
 * confirmed it's what you want, not as a side effect of deploying this
 * code. A CDN/WAF-level geo-block (Cloudflare etc.) is the more robust
 * place for this control if you have one in front of the app; this is a
 * defense-in-depth layer that works even without one.
 */
class GeoRestrict extends BaseConfig
{
    /**
     * Set `GeoRestrict.enabled = true` in .env to turn this on. Leave it
     * off in dev/staging — every request from outside the allowed
     * ranges gets a 403, which is exactly what you don't want while
     * testing from a laptop that isn't in India.
     */
    public bool $enabled = false;

    /**
     * IPs that are always allowed regardless of the geo-block —
     * loopback/private ranges so local health checks, a misconfigured
     * reverse-proxy header, or `php spark serve` testing never lock
     * anyone out by accident. Add your office/VPN egress IP here if you
     * need guaranteed access from outside India (e.g. a developer
     * abroad) without disabling the block entirely.
     *
     * @var list<string>
     */
    public array $alwaysAllowIps = [
        '127.0.0.1',
        '::1',
    ];

    /**
     * Route prefixes exempt from the geo-block even when enabled.
     * Payment-gateway webhooks must never be blocked here — Cashfree's
     * own servers are not in India, and blocking their callback would
     * silently break every online payment. Verified separately by the
     * webhook's own HMAC signature check, not by this filter.
     *
     * @var list<string>
     */
    public array $exemptRoutePrefixes = [
        'webhooks/',
    ];
}

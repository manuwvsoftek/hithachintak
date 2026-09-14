<?php

declare(strict_types=1);

namespace App\Libraries\Security;

/**
 * Checks whether an IP address falls inside a bundled list of CIDR
 * ranges (IPv4 and IPv6 data files under data/) — backs GeoRestrict's
 * India-only mode. The ranges come from RIR delegation data (see the
 * header comment in each data file for source/fetch date); allocations
 * change over time, so this data should be refreshed periodically
 * rather than treated as permanently accurate.
 */
class CidrCountryList
{
    /** @var list<array{0:string,1:int}>|null */
    private static ?array $ipv4Ranges = null;

    /** @var list<array{0:string,1:int}>|null */
    private static ?array $ipv6Ranges = null;

    public static function contains(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        $isV4 = strlen($packed) === 4;
        $ranges = $isV4 ? self::loadIpv4() : self::loadIpv6();

        foreach ($ranges as [$rangePacked, $prefixLen]) {
            if (self::packedInRange($packed, $rangePacked, $prefixLen)) {
                return true;
            }
        }

        return false;
    }

    private static function packedInRange(string $ip, string $range, int $prefixLen): bool
    {
        $bytes = intdiv($prefixLen, 8);
        $remainderBits = $prefixLen % 8;

        if ($bytes > 0 && substr($ip, 0, $bytes) !== substr($range, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainderBits) & 0xFF;

        return (ord($ip[$bytes]) & $mask) === (ord($range[$bytes]) & $mask);
    }

    /** @return list<array{0:string,1:int}> */
    private static function loadIpv4(): array
    {
        return self::$ipv4Ranges ??= self::loadFile(__DIR__ . '/data/india-ipv4.txt');
    }

    /** @return list<array{0:string,1:int}> */
    private static function loadIpv6(): array
    {
        return self::$ipv6Ranges ??= self::loadFile(__DIR__ . '/data/india-ipv6.txt');
    }

    /** @return list<array{0:string,1:int}> */
    private static function loadFile(string $path): array
    {
        $cache = service('cache');
        $cacheKey = 'georestrict_cidr_' . md5($path);
        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $ranges = [];
        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || ! str_contains($line, '/')) {
                    continue;
                }
                [$addr, $prefix] = explode('/', $line, 2);
                $packed = @inet_pton($addr);
                if ($packed === false) {
                    continue;
                }
                $ranges[] = [$packed, (int) $prefix];
            }
        }

        // A day is plenty — this only changes when the bundled data files
        // are redeployed, and re-parsing ~10k lines per request would be
        // wasteful otherwise.
        $cache->save($cacheKey, $ranges, DAY);

        return $ranges;
    }
}

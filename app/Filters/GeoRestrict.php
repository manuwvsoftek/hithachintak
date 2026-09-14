<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Security\CidrCountryList;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\GeoRestrict as GeoRestrictConfig;

/**
 * India-only access gate — see Config\GeoRestrict for what this does and
 * doesn't cover, and why it's off by default. Registered globally in
 * Config\Filters; this class only checks Config\GeoRestrict::$enabled,
 * never anything route-specific, so the actual exemptions live in that
 * config (exemptRoutePrefixes) rather than being duplicated here.
 */
class GeoRestrict implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(GeoRestrictConfig::class);
        if (! $config->enabled) {
            return null;
        }

        $path = ltrim($request->getPath(), '/');
        foreach ($config->exemptRoutePrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return null;
            }
        }

        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $ip = $request->getIPAddress();

        if (in_array($ip, $config->alwaysAllowIps, true)) {
            return null;
        }

        if (CidrCountryList::contains($ip)) {
            return null;
        }

        log_message('notice', 'GeoRestrict blocked a request from outside India: ip={ip} path={path}', [
            'ip'   => $ip,
            'path' => $path,
        ]);

        return service('response')
            ->setStatusCode(403)
            ->setBody('Access to this service is restricted to India.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

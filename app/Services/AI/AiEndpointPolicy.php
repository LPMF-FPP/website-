<?php

namespace App\Services\AI;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Process\Process;

class AiEndpointPolicy
{
    public function validate(string $url): string
    {
        $parts = parse_url($url);
        $hosts = config('services.ai.approved_hosts', []);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ! in_array($parts['host'] ?? '', $hosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! preg_match('#^/[a-zA-Z0-9/_-]+/?$#', $parts['path'] ?? '')) {
            throw new AiFailure('AI_CONFIGURATION_INVALID', 'Endpoint AI harus memakai HTTPS, host yang disetujui, dan port 443 tanpa kredensial atau query. Periksa konfigurasi AI.', 422);
        }

        return rtrim($url, '/');
    }

    public function resolve(string $host): array
    {
        try {
            $process = new Process(['getent', 'ahosts', $host]);
            $process->setTimeout(max(0.1, (float) config('services.ai.dns_timeout', 3)));
            $process->run();
        } catch (\Throwable) {
            return [];
        }

        if (! $process->isSuccessful()) {
            return [];
        }

        $addresses = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
            $ip = preg_split('/\s+/', trim($line))[0] ?? null;
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $addresses[] = $ip;
            }
        }

        return array_values(array_unique($addresses));
    }

    public function connectionOptions(string $url): array
    {
        $url = $this->validate($url);
        $host = parse_url($url, PHP_URL_HOST);
        $addresses = $this->resolve($host);
        $reserved = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '::/96', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001::/23', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
        if ($addresses === [] || ! extension_loaded('curl')) {
            throw new AiFailure('AI_DESTINATION_INVALID', 'Tujuan AI tidak dapat diverifikasi. Periksa DNS dan dukungan cURL server.', 422);
        }
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || IpUtils::checkIp($ip, $reserved)
                || IpUtils::checkIp($ip, ['192.88.99.0/24', '2001:db8::/32', '3fff::/20'])
                || (str_contains($ip, ':') && ! IpUtils::checkIp($ip, '2000::/3'))) {
                throw new AiFailure('AI_DESTINATION_INVALID', 'Endpoint AI mengarah ke alamat privat atau khusus. Hubungi administrator.', 422);
            }
        }

        // Pin the validated address while retaining the original TLS hostname and certificate verification.
        $ip = $addresses[0];
        $pinned = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        return ['allow_redirects' => false, 'verify' => true, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$pinned], CURLOPT_PROXY => '']];
    }
}

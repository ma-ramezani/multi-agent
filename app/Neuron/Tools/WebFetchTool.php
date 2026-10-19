<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

class WebFetchTool extends Tool
{
    protected string $name = 'web_fetch';
    protected ?string $description = 'Fetch the text content of a PUBLIC web page URL. Internal addresses (localhost, private networks, cloud metadata) are blocked.';

    /**
     * IPv4/IPv6 ranges that must never be fetched (SSRF protection).
     */
    protected static function isBlockedIp(string $ip): bool
    {
        // metadata + link-local + unspecified
        if (str_starts_with($ip, '169.254.') || $ip === '0.0.0.0' || $ip === '::') {
            return true;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return true; // unparseable → refuse
        }
        $isV4 = strlen($bin) === 4;
        $inRange = function (string $cidr) use ($bin, $isV4): bool {
            [$net, $bits] = explode('/', $cidr);
            $netBin = @inet_pton($net);
            if ($netBin === false || strlen($netBin) !== strlen($bin)) {
                return false;
            }
            $bytes = intdiv((int) $bits, 8);
            $rem = ((int) $bits) % 8;
            if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
                return false;
            }
            if ($rem > 0) {
                $mask = 0xFF & (0xFF << (8 - $rem));
                return ((ord($bin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask));
            }
            return true;
        };
        foreach (['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7', 'fe80::/10'] as $cidr) {
            if ($isV4 && str_contains($cidr, ':')) {
                continue;
            }
            if (!$isV4 && !str_contains($cidr, ':')) {
                continue;
            }
            if ($inRange($cidr)) {
                return true;
            }
        }
        return false;
    }

    protected static function hostAllowed(string $host): bool
    {
        $host = strtolower(trim($host, '.'));
        if ($host === '' || $host === 'localhost') {
            return false;
        }
        // literal IP?
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !self::isBlockedIp($host);
        }
        // resolve and check every A/AAAA record (mitigates DNS rebinding for casual use)
        $ips = [];
        foreach (['A', 'AAAA'] as $type) {
            $recs = @dns_get_record($host, $type === 'A' ? DNS_A : DNS_AAAA);
            foreach ($recs ?: [] as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }
        if ($ips === []) {
            // unresolvable via DNS_* (e.g. /etc/hosts-only names like internal domains) → refuse
            $fallback = @gethostbyname($host);
            if ($fallback === $host) {
                return false;
            }
            $ips = [$fallback];
        }
        foreach (array_filter($ips) as $ip) {
            if (self::isBlockedIp($ip)) {
                return false;
            }
        }
        return true;
    }

    protected function properties(): array
    {
        return [
            new ToolProperty('url', PropertyType::STRING, 'Full http(s) URL to fetch', true),
        ];
    }

    public function __invoke(string $url): ToolOutput
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ToolOutput::text('Invalid URL. Must start with http:// or https://');
        }
        $parts = @parse_url($url);
        if (empty($parts['host']) || !self::hostAllowed($parts['host'])) {
            return ToolOutput::text('Blocked by security policy: internal or unresolvable hosts cannot be fetched. Only public web pages are allowed.');
        }
        try {
            // no auto-redirect following: validate every hop against the same policy
            $current = $url;
            $html = false;
            for ($hop = 0; $hop < 3; $hop++) {
                $ctx = stream_context_create(['http' => [
                    'timeout' => 15,
                    'follow_location' => 0,
                    'header' => "User-Agent: NeuronAgent/1.0\r\n",
                ]]);
                $html = @file_get_contents($current, false, $ctx, 0, 500000);
                if ($html === false) {
                    return ToolOutput::text("Could not fetch URL: $url");
                }
                $status = 200;
                $next = null;
                foreach ($http_response_header ?? [] as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d+)#i', $h, $m)) {
                        $status = (int) $m[1];
                    } elseif (preg_match('#^Location:\s*(\S+)#i', $h, $m)) {
                        $next = trim($m[1]);
                    }
                }
                if (in_array($status, [301, 302, 303, 307, 308]) && $next) {
                    // resolve relative redirect
                    if (!preg_match('#^https?://#i', $next)) {
                        $p = @parse_url($current);
                        $next = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (str_starts_with($next, '/') ? $next : '/' . $next);
                    }
                    $np = @parse_url($next);
                    if (empty($np['host']) || !self::hostAllowed($np['host']) || !preg_match('#^https?://#i', $next)) {
                        return ToolOutput::text('Blocked by security policy: redirect target is not an allowed public URL.');
                    }
                    $current = $next;
                    continue;
                }
                break;
            }
            $text = strip_tags($html);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);
            return ToolOutput::text(mb_substr(trim($text), 0, 12000));
        } catch (\Throwable $e) {
            return ToolOutput::text('Fetch error: ' . $e->getMessage());
        }
    }
}

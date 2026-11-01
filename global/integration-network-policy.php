<?php

function app_integration_address_in_cidr(string $address, string $range): bool
{
    [$network, $prefix] = explode('/', $range, 2);
    $packedAddress = inet_pton($address);
    $packedNetwork = inet_pton($network);
    if ($packedAddress === false || $packedNetwork === false || strlen($packedAddress) !== strlen($packedNetwork)) return false;
    $prefix = (int) $prefix;
    $wholeBytes = intdiv($prefix, 8);
    if (substr($packedAddress, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) return false;
    $remainingBits = $prefix % 8;
    if ($remainingBits === 0) return true;
    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($packedAddress[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
}

function app_integration_is_public_address(string $address): bool
{
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    $reservedRanges = strlen((string) inet_pton($address)) === 4
        ? ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24',
            '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']
        : ['::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48',
            '100::/64', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
    foreach ($reservedRanges as $range) {
        if (app_integration_address_in_cidr($address, $range)) return false;
    }
    return true;
}

function app_integration_public_destination(string $url, array $allowedPrivateHosts = []): array
{
    $parts = parse_url($url);
    if (!is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || isset($parts['user'])
        || isset($parts['pass'])) {
        throw new InvalidArgumentException('La URL de integración no es válida.');
    }

    $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
    $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));
    if ($host === '' || $port < 1 || $port > 65535) {
        throw new InvalidArgumentException('La URL de integración no es válida.');
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $addresses = [$host];
    } else {
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException('El nombre del servidor de integración no es válido.');
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) $addresses[] = $record['ip'];
                if (isset($record['ipv6'])) $addresses[] = $record['ipv6'];
            }
        }
    }

    $addresses = array_values(array_unique($addresses));
    if (!$addresses) {
        throw new InvalidArgumentException('No se pudo resolver el servidor de integración.');
    }
    $allowedPrivateHosts = array_map(
        static fn(string $allowedHost): string => strtolower(rtrim(trim($allowedHost, '[] '), '.')),
        $allowedPrivateHosts
    );
    $privateHostAllowed = in_array($host, $allowedPrivateHosts, true);
    foreach ($addresses as $address) {
        if (!app_integration_is_public_address($address) && !$privateHostAllowed) {
            throw new InvalidArgumentException('La integración no puede apuntar a redes privadas, locales o reservadas.');
        }
    }

    return ['host' => $host, 'port' => $port, 'address' => $addresses[0]];
}

function app_integration_curl_resolution(array $destination): ?string
{
    if (filter_var($destination['host'], FILTER_VALIDATE_IP) !== false) return null;
    $address = $destination['address'];
    if (strpos($address, ':') !== false) $address = '[' . $address . ']';
    return $destination['host'] . ':' . $destination['port'] . ':' . $address;
}

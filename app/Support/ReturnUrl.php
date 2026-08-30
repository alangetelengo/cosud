<?php

namespace App\Support;

class ReturnUrl
{
    public static function current(): string
    {
        return url()->full();
    }

    public static function resolve(?string $return, string $fallback): string
    {
        return self::validated($return) ?? $fallback;
    }

    public static function validated(?string $return): ?string
    {
        if ($return === null || $return === '') {
            return null;
        }

        if (str_starts_with($return, '/') && ! str_starts_with($return, '//')) {
            return url($return);
        }

        $candidate = parse_url($return);
        $app = parse_url(url('/'));

        if ($candidate === false || $app === false) {
            return null;
        }

        if (! isset($candidate['scheme'], $candidate['host'], $app['scheme'], $app['host'])) {
            return null;
        }

        if (isset($candidate['user']) || isset($candidate['pass'])) {
            return null;
        }

        if (strcasecmp((string) $candidate['scheme'], (string) $app['scheme']) !== 0) {
            return null;
        }

        if (strcasecmp((string) $candidate['host'], (string) $app['host']) !== 0) {
            return null;
        }

        $candidatePort = isset($candidate['port'])
            ? (int) $candidate['port']
            : self::portParDefaut((string) $candidate['scheme']);
        $appPort = isset($app['port'])
            ? (int) $app['port']
            : self::portParDefaut((string) $app['scheme']);

        if ($candidatePort !== $appPort) {
            return null;
        }

        return $return;
    }

    /**
     * @param  array<int|string, mixed>|object|null  $parameters
     * @return array<int|string, mixed>
     */
    public static function forRoute(mixed $parameters = [], ?string $return = null): array
    {
        $returnParam = ['return' => $return ?? self::current()];

        if (is_array($parameters)) {
            return array_merge($parameters, $returnParam);
        }

        if ($parameters === null) {
            return $returnParam;
        }

        return array_merge([$parameters], $returnParam);
    }

    /**
     * Propage un paramètre return existant sans en inventer un nouveau.
     *
     * @param  array<int|string, mixed>|object|null  $parameters
     * @return array<int|string, mixed>|object|null
     */
    public static function propagate(mixed $parameters, ?string $return): mixed
    {
        if ($return === null || $return === '') {
            return $parameters;
        }

        return self::forRoute($parameters, $return);
    }

    private static function portParDefaut(string $scheme): int
    {
        return strcasecmp($scheme, 'https') === 0 ? 443 : 80;
    }
}

<?php

namespace ScoutingMemories\Forms\Support;

/**
 * Environment
 *
 * Tells local development sites (DDEV at *.ddev.site, earlier Docker at localhost:8088) apart
 * from the live site, so test-only behaviour (mail interception, test-entry marking) can never
 * run on live.
 */
class Environment {

    /**
     * True on a local development copy: the site URL host is localhost, 127.0.0.1 or a
     * *.local / *.test / *.ddev.site domain (ddev.site only ever points at the computer itself).
     */
    public static function isLocal(): bool {
        static $isLocal = null;
        if ($isLocal === null) {
            $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
            $isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
                || substr($host, -6) === '.local'
                || substr($host, -5) === '.test'
                || substr($host, -10) === '.ddev.site';
        }
        return $isLocal;
    }
}

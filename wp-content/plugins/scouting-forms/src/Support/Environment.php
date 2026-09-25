<?php

namespace ScoutingMemories\Forms\Support;

/**
 * Environment
 *
 * Tells the local development site (Docker at localhost:8088) apart from the live site, so
 * test-only behaviour (mail interception, test-entry marking) can never run on live.
 */
class Environment {

    /**
     * True on a local development copy: the site URL host is localhost, 127.0.0.1 or a
     * *.local / *.test domain.
     */
    public static function isLocal(): bool {
        static $isLocal = null;
        if ($isLocal === null) {
            $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
            $isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
                || substr($host, -6) === '.local'
                || substr($host, -5) === '.test';
        }
        return $isLocal;
    }
}

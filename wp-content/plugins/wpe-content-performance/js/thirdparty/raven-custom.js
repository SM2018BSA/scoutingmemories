/* global Raven, wpecpraven */
Raven.config('https://b413935fb63140038a35c0f209440aac@sentry.io/145499', {
  'release': wpecpraven.release,
  'environment': wpecpraven.environment,
  'whitelistUrls': [
    /(inc\/js\/app\.js|wpe-content-performance-settings\.js)/
  ],
}).install();

Raven.setUserContext({
  username: wpecpraven.username,
});

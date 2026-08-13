# Origin Support Bot

Embeds the Origin CMS support assistant chat widget for editors. All chat
UI and bot logic live in the remote support-bot app — this module only:

- signs a short-lived HS256 token (`site_id`, role, current path, and the
  user's email since 1.1.0) so the widget authenticates seamlessly and
  neither site identity nor the ticket-form requester can be spoofed;
- attaches the loader script (`<endpoint>/widget.js`) only for roles with
  the **Use support assistant** permission (anonymous users never load it);
- injects `site_id`, current path, endpoint, and the token via
  `drupalSettings.originSupportBot`.

No routes, no config forms, no database.

## Install

```bash
composer require origindesign/origin_support_bot
drush en origin_support_bot
```

Add the shared secret to `settings.php` (NOT a config form — config export
would commit the secret to git). Value must match the app's
`SUPPORT_BOT_WIDGET_SECRET` environment variable:

```php
$settings['support_bot_secret'] = 'the-agency-wide-secret';
```

Grant the **Use support assistant** permission to editor roles.

## Site identity

`site_id` comes from `$_ENV['PANTHEON_SITE_NAME']` — zero per-site config
on Pantheon. Off Pantheon (e.g. Lando), set it explicitly:

```php
$settings['support_bot_site_id'] = 'origin-drop-11';
```

If the secret or site id is missing the widget silently stays off.

## Endpoint override

Defaults to the production app
(`https://drupal-cms-support-agent.netlify.app` — update the constant in
`origin_support_bot.module` when the custom domain lands). Point local dev
at a local app build
(cache rebuild required — library definitions are cached):

```php
$settings['support_bot_endpoint'] = 'http://localhost:3000';
```

## Caching

The token is minted by an uncacheable lazy builder placeholder, so it is
fresh on every request and never frozen into page or dynamic page cache.
Token TTL is 1 hour; the widget asks for a page reload if it expires.

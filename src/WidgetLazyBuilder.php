<?php

namespace Drupal\origin_support_bot;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Site\Settings;

/**
 * Lazy builder that attaches the widget library and a signed identity token.
 *
 * Runs on every request (max-age 0 placeholder) so the token is always
 * fresh. Output is empty when the user lacks permission or the module is
 * not configured, so the placeholder costs nothing on those pages.
 */
final class WidgetLazyBuilder implements TrustedCallbackInterface {

  /**
   * Token lifetime in seconds. Keep in sync with the app's verifier.
   */
  private const TOKEN_TTL = 3600;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['attach'];
  }

  /**
   * Builds the widget attachment render array.
   */
  public static function attach(): array {
    $build = ['#cache' => ['max-age' => 0]];

    if (!\Drupal::currentUser()->hasPermission('use support assistant')) {
      return $build;
    }

    $secret = Settings::get('support_bot_secret');
    $site_id = getenv('PANTHEON_SITE_NAME') ?: Settings::get('support_bot_site_id');
    if (!$secret || !$site_id) {
      // Not configured for this environment; stay silent rather than break
      // page rendering.
      return $build;
    }

    $path = \Drupal::service('path.current')->getPath();

    $build['#attached'] = [
      'library' => ['origin_support_bot/widget'],
      'drupalSettings' => [
        'originSupportBot' => [
          'endpoint' => _origin_support_bot_endpoint(),
          'siteId' => $site_id,
          'path' => $path,
          'token' => self::signToken($secret, $site_id, $path),
        ],
      ],
    ];
    return $build;
  }

  /**
   * Signs a compact JWT (HS256) identifying this site and page.
   *
   * Hand-rolled on purpose: HMAC-SHA256 over two base64url segments needs no
   * composer dependency and stays byte-compatible with standard JWT
   * verifiers on the app side.
   */
  private static function signToken(string $secret, string $site_id, string $path): string {
    $now = \Drupal::time()->getRequestTime();
    $segments = [
      self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
      self::b64(json_encode([
        'site_id' => $site_id,
        'path' => $path,
        'role' => 'editor',
        'iat' => $now,
        'exp' => $now + self::TOKEN_TTL,
      ])),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $secret, TRUE);
    $segments[] = self::b64($signature);
    return implode('.', $segments);
  }

  /**
   * Base64url encodes without padding (RFC 7515).
   */
  private static function b64(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

}

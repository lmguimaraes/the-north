<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

header_remove('X-Powered-By');

function tnqr_admin_request_is_https(): bool
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        ? (string) $_SERVER['HTTP_X_FORWARDED_PROTO']
        : '';
    if ($forwardedProto !== '') {
        $firstProto = strtolower(trim(explode(',', $forwardedProto)[0]));
        if ($firstProto === 'https') {
            return true;
        }
    }

    return isset($_SERVER['HTTP_X_FORWARDED_SSL'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on';
}

function tnqr_admin_request_host(): string
{
    $hostHeader = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($hostHeader === '') {
        return '';
    }

    $parsed = parse_url('http://' . $hostHeader, PHP_URL_HOST);
    return is_string($parsed) ? strtolower(rtrim($parsed, '.')) : '';
}

$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '';
$requestHost = tnqr_admin_request_host();
$requestIsHttps = tnqr_admin_request_is_https();
$canonicalHost = strtolower(TNQR_CANONICAL_HOST);
$isProductionHost = $requestHost === $canonicalHost || $requestHost === 'www.' . $canonicalHost;

// Enforce the final HTTPS admin address on the live domain.
if (
    $isProductionHost
    && (!$requestIsHttps || $requestHost !== $canonicalHost || $requestPath !== TNQR_ADMIN_PATH)
) {
    header('Location: ' . TNQR_ADMIN_URL, true, 308);
    exit;
}

// Keep one canonical trailing-slash route in non-production environments too.
if (
    !$isProductionHost
    && ($requestPath === rtrim(TNQR_ADMIN_PATH, '/') || $requestPath === TNQR_ADMIN_PATH . 'index.php')
) {
    header('Location: ' . TNQR_ADMIN_PATH, true, 308);
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Permissions-Policy: camera=(), geolocation=(), microphone=(), payment=(), usb=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; connect-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

$tnqrCookieIsSecure = $requestIsHttps;

// Remove cookies created by earlier development packages.
foreach (['tn_qr_admin', 'tn_qr_admin_v4'] as $legacyCookieName) {
    if (!isset($_COOKIE[$legacyCookieName])) {
        continue;
    }

    foreach ([TNQR_ADMIN_PATH, rtrim(TNQR_ADMIN_PATH, '/'), TNQR_PUBLIC_PATH] as $legacyPath) {
        setcookie($legacyCookieName, '', [
            'expires' => time() - 42000,
            'path' => $legacyPath,
            'secure' => $tnqrCookieIsSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', (string) TNQR_SESSION_TIMEOUT_SECONDS);

session_name(TNQR_SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => TNQR_ADMIN_PATH,
    'secure' => $tnqrCookieIsSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (!session_start()) {
    http_response_code(500);
    exit('The admin session could not be started.');
}

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function tnqr_admin_redirect(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Location: ' . TNQR_ADMIN_PATH, true, 303);
    exit;
}

function tnqr_admin_set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type:string,message:string}|null */
function tnqr_admin_take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
        return null;
    }

    return [
        'type' => (string) $flash['type'],
        'message' => (string) $flash['message'],
    ];
}

function tnqr_admin_verify_csrf(): bool
{
    $submitted = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    $known = isset($_SESSION['csrf']) ? (string) $_SESSION['csrf'] : '';
    return $submitted !== '' && $known !== '' && hash_equals($known, $submitted);
}

function tnqr_admin_is_logged_in(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function tnqr_admin_lock_message(int $lockedUntil): string
{
    $minutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
    return 'Too many incorrect attempts. Try again in about ' . $minutes . ' minute' . ($minutes === 1 ? '.' : 's.');
}

if (tnqr_admin_is_logged_in()) {
    $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : 0;
    if ($lastActivity > 0 && time() - $lastActivity > TNQR_SESSION_TIMEOUT_SECONDS) {
        unset($_SESSION['authenticated'], $_SESSION['last_activity']);
        @session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        tnqr_admin_set_flash('error', 'Your session expired. Enter the PIN again.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if (!tnqr_admin_verify_csrf()) {
        tnqr_admin_set_flash('error', 'The form expired. Please try again.');
        tnqr_admin_redirect();
    }

    if ($action === 'login') {
        $rateStatus = tnqr_begin_login_attempt();
        if (!$rateStatus['allowed']) {
            tnqr_admin_set_flash('error', tnqr_admin_lock_message($rateStatus['lockedUntil']));
            tnqr_admin_redirect();
        }

        $pin = isset($_POST['pin']) ? trim((string) $_POST['pin']) : '';

        try {
            $storeForLogin = tnqr_read_store();
            $pinHash = $storeForLogin['pinHash'];
        } catch (Throwable $exception) {
            error_log('The North QR admin login read error: ' . $exception->getMessage());
            tnqr_clear_login_failures();
            tnqr_admin_set_flash('error', 'The manager is temporarily unavailable. Contact the site administrator.');
            tnqr_admin_redirect();
        }

        if (preg_match('/^[0-9]{6}$/', $pin) === 1 && password_verify($pin, $pinHash)) {
            if (!session_regenerate_id(true)) {
                error_log('The North QR admin session could not be regenerated after login.');
                tnqr_admin_set_flash('error', 'The secure session could not be started. Please try again.');
                tnqr_admin_redirect();
            }

            tnqr_clear_login_failures();
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            tnqr_admin_set_flash('success', 'Signed in.');
        } else {
            $rateStatus = tnqr_finalize_login_failure();
            usleep(350000);

            if ($rateStatus['lockedUntil'] > time()) {
                tnqr_admin_set_flash('error', tnqr_admin_lock_message($rateStatus['lockedUntil']));
            } else {
                tnqr_admin_set_flash('error', 'Incorrect PIN.');
            }
        }

        tnqr_admin_redirect();
    }

    if (!tnqr_admin_is_logged_in()) {
        tnqr_admin_set_flash('error', 'Enter the PIN to continue.');
        tnqr_admin_redirect();
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) $params['path'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        header('Location: ' . TNQR_ADMIN_PATH, true, 303);
        exit;
    }

    try {
        $store = tnqr_read_store();

        if ($action === 'add') {
            $label = trim((string) ($_POST['label'] ?? ''));
            $url = trim((string) ($_POST['url'] ?? ''));

            if ($label === '' || tnqr_text_length($label) > 80) {
                throw new InvalidArgumentException('Enter a link name between 1 and 80 characters.');
            }
            if (!tnqr_is_valid_http_url($url)) {
                throw new InvalidArgumentException('Enter a complete http:// or https:// URL.');
            }
            if (tnqr_is_self_redirect($url)) {
                throw new InvalidArgumentException('The destination cannot point to the /go area of this website.');
            }
            if (count($store['links']) >= TNQR_MAX_LINKS) {
                throw new InvalidArgumentException('The saved-link limit has been reached.');
            }

            foreach ($store['links'] as $existing) {
                if (rtrim(strtolower($existing['url']), '/') === rtrim(strtolower($url), '/')) {
                    throw new InvalidArgumentException('That URL is already saved.');
                }
            }

            $store['links'][] = [
                'id' => tnqr_new_id(),
                'label' => $label,
                'url' => $url,
                'createdAt' => gmdate('c'),
            ];
            tnqr_write_store($store);
            tnqr_admin_set_flash('success', 'Link added. Activate it when the promotion is ready.');
        } elseif ($action === 'activate') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $found = false;
            foreach ($store['links'] as $link) {
                if ($link['id'] === $id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new InvalidArgumentException('That saved link no longer exists.');
            }

            $store['activeId'] = $id;
            tnqr_write_store($store);
            tnqr_admin_set_flash('success', 'The QR redirect target is now active.');
        } elseif ($action === 'delete') {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === $store['activeId']) {
                throw new InvalidArgumentException('Activate another link before removing the current target.');
            }

            $before = count($store['links']);
            $store['links'] = array_values(array_filter(
                $store['links'],
                static function (array $link) use ($id): bool {
                    return $link['id'] !== $id;
                }
            ));
            if (count($store['links']) === $before) {
                throw new InvalidArgumentException('That saved link no longer exists.');
            }

            tnqr_write_store($store);
            tnqr_admin_set_flash('success', 'Link removed.');
        } elseif ($action === 'change_pin') {
            $currentPin = trim((string) ($_POST['current_pin'] ?? ''));
            $newPin = trim((string) ($_POST['new_pin'] ?? ''));
            $confirmPin = trim((string) ($_POST['confirm_pin'] ?? ''));

            if (preg_match('/^[0-9]{6}$/', $currentPin) !== 1 || !password_verify($currentPin, $store['pinHash'])) {
                throw new InvalidArgumentException('The current PIN is incorrect.');
            }
            if (preg_match('/^[0-9]{6}$/', $newPin) !== 1) {
                throw new InvalidArgumentException('The new PIN must contain exactly 6 digits.');
            }
            if ($newPin === '000000') {
                throw new InvalidArgumentException('Choose a PIN other than the default 000000.');
            }
            if ($newPin !== $confirmPin) {
                throw new InvalidArgumentException('The two new PIN entries do not match.');
            }
            if (password_verify($newPin, $store['pinHash'])) {
                throw new InvalidArgumentException('Choose a PIN different from the current PIN.');
            }

            $newHash = password_hash($newPin, PASSWORD_DEFAULT);
            if (!is_string($newHash) || $newHash === '') {
                throw new RuntimeException('The new PIN could not be secured.');
            }

            $store['pinHash'] = $newHash;
            tnqr_write_store($store);
            @session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            tnqr_clear_login_failures();
            tnqr_admin_set_flash('success', 'PIN changed.');
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    } catch (InvalidArgumentException $exception) {
        tnqr_admin_set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('The North QR admin error: ' . $exception->getMessage());
        tnqr_admin_set_flash('error', 'The change could not be saved. Check that go/data is writable.');
    }

    tnqr_admin_redirect();
}

$flash = tnqr_admin_take_flash();
$loggedIn = tnqr_admin_is_logged_in();
$store = null;
$storeError = null;
$usingDefaultPin = false;

if ($loggedIn) {
    try {
        $store = tnqr_read_store();
        $usingDefaultPin = tnqr_uses_default_pin($store['pinHash']);
    } catch (Throwable $exception) {
        $storeError = 'The saved links could not be loaded. Contact the site administrator.';
        error_log('The North QR admin render error: ' . $exception->getMessage());
    }
}

$csrf = (string) $_SESSION['csrf'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <title>The North QR Link Manager</title>
  <link rel="stylesheet" href="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>assets/admin.css?v=6">
  <script src="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>assets/admin.js?v=6" defer></script>
</head>
<body>
  <main class="page-shell">
    <section class="admin-card<?= $loggedIn ? ' admin-card--wide' : '' ?>" aria-labelledby="page-title">
      <header class="brand-header">
        <img class="brand-logo" src="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-logo-transparent.png" alt="The North Latin Festival">
        <div>
          <p class="eyebrow">Dynamic QR</p>
          <h1 id="page-title">Link Manager</h1>
          <p class="intro">The printed QR always opens <strong><?= tnqr_escape(TNQR_PUBLIC_URL) ?></strong>. Choose where it redirects below.</p>
        </div>
      </header>

      <?php if ($flash !== null): ?>
        <div class="notice notice--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
          <?= tnqr_escape($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if (!$loggedIn): ?>
        <form class="login-form" method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>" autocomplete="on">
          <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
          <input type="hidden" name="action" value="login">
          <label for="pin">6-digit PIN</label>
          <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password" autofocus required>
          <button class="button button--primary" type="submit">Open manager</button>
        </form>
        <p class="login-note">Use the PIN provided by the site administrator.</p>
      <?php else: ?>
        <div class="top-actions">
          <a class="button button--secondary" href="<?= tnqr_escape(TNQR_PUBLIC_PATH) ?>" target="_blank" rel="noopener noreferrer">Open current destination</a>
          <form method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>">
            <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="button button--quiet" type="submit">Sign out</button>
          </form>
        </div>

        <?php if ($storeError !== null || $store === null): ?>
          <div class="notice notice--error" role="alert"><?= tnqr_escape((string) $storeError) ?></div>
        <?php else: ?>
          <?php if ($usingDefaultPin): ?>
            <div class="notice notice--error" role="alert">
              The default PIN is still active. Change it in the Security section before sharing access to this manager.
            </div>
          <?php endif; ?>

          <section class="summary-grid" aria-label="Current redirect destination">
            <div class="summary-copy">
              <p class="eyebrow">Current destination</p>
              <p class="active-url"><?= tnqr_escape(tnqr_active_url($store)) ?></p>
              <p class="summary-note">Both QR versions below always open <strong><?= tnqr_escape(TNQR_PUBLIC_URL) ?></strong> and use this active destination.</p>
            </div>
          </section>

          <section class="manager-section" aria-labelledby="qr-downloads-title">
            <div class="section-heading">
              <div>
                <p class="eyebrow">Print &amp; digital</p>
                <h2 id="qr-downloads-title">QR downloads</h2>
              </div>
              <span class="count-badge">2 versions</span>
            </div>
            <p class="section-intro">Choose the branded version with the festival logo, or the plain version without a centre overlay. Both encode the same permanent dynamic address.</p>

            <div class="qr-options">
              <article class="qr-option">
                <div class="qr-preview qr-preview--download">
                  <img src="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-web.png" alt="The North dynamic QR code with festival logo">
                </div>
                <div class="qr-option-copy">
                  <div class="qr-option-heading">
                    <h3>Branded QR</h3>
                    <span class="option-badge">With logo</span>
                  </div>
                  <p class="qr-option-description">Festival logo on a white centre panel with square corners.</p>
                  <div class="download-row download-row--buttons">
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr.svg" download>Vector SVG</a>
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-print.png" download>Print PNG</a>
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-web.png" download>Web PNG</a>
                  </div>
                </div>
              </article>

              <article class="qr-option">
                <div class="qr-preview qr-preview--download">
                  <img src="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-no-logo-web.png" alt="The North dynamic QR code without a logo">
                </div>
                <div class="qr-option-copy">
                  <div class="qr-option-heading">
                    <h3>Plain QR</h3>
                    <span class="option-badge">No logo</span>
                  </div>
                  <p class="qr-option-description">No centre overlay. Use this version for smaller placements or the greatest scanning margin.</p>
                  <div class="download-row download-row--buttons">
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-no-logo.svg" download>Vector SVG</a>
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-no-logo-print.png" download>Print PNG</a>
                    <a class="button button--secondary button--small" href="<?= tnqr_escape(TNQR_QR_ASSET_PATH) ?>the-north-dynamic-qr-no-logo-web.png" download>Web PNG</a>
                  </div>
                </div>
              </article>
            </div>
          </section>

          <section class="manager-section" aria-labelledby="saved-links-title">
            <div class="section-heading">
              <div>
                <p class="eyebrow">Destinations</p>
                <h2 id="saved-links-title">Saved links</h2>
              </div>
              <span class="count-badge"><?= count($store['links']) ?> saved</span>
            </div>

            <div class="link-list">
              <?php foreach ($store['links'] as $link): ?>
                <?php $isActive = $link['id'] === $store['activeId']; ?>
                <article class="link-item<?= $isActive ? ' link-item--active' : '' ?>">
                  <div class="link-main">
                    <div class="link-title-row">
                      <h3><?= tnqr_escape($link['label']) ?></h3>
                      <?php if ($isActive): ?><span class="active-badge">Current</span><?php endif; ?>
                    </div>
                    <a class="saved-url" href="<?= tnqr_escape($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= tnqr_escape($link['url']) ?></a>
                  </div>
                  <div class="link-actions">
                    <?php if (!$isActive): ?>
                      <form method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>">
                        <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?= tnqr_escape($link['id']) ?>">
                        <button class="button button--primary button--small" type="submit">Activate</button>
                      </form>
                      <form class="js-delete-form" method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>" data-confirm="Remove this saved link?">
                        <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= tnqr_escape($link['id']) ?>">
                        <button class="button button--danger button--small" type="submit">Remove</button>
                      </form>
                    <?php else: ?>
                      <span class="current-note">QR scans here now</span>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="manager-section manager-section--soft" aria-labelledby="add-link-title">
            <div class="section-heading">
              <div>
                <p class="eyebrow">New destination</p>
                <h2 id="add-link-title">Add a link</h2>
              </div>
            </div>
            <form class="add-form" method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>">
              <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
              <input type="hidden" name="action" value="add">
              <div class="field">
                <label for="label">Name</label>
                <input id="label" name="label" type="text" maxlength="80" placeholder="Example: Early bird tickets" required>
              </div>
              <div class="field field--url">
                <label for="url">Full URL</label>
                <input id="url" name="url" type="url" maxlength="2048" placeholder="https://..." required>
              </div>
              <button class="button button--primary" type="submit">Save link</button>
            </form>
          </section>

          <details class="security-panel"<?= $usingDefaultPin ? ' open' : '' ?>>
            <summary>Security: change the 6-digit PIN</summary>
            <form class="pin-form" method="post" action="<?= tnqr_escape(TNQR_ADMIN_PATH) ?>">
              <input type="hidden" name="csrf" value="<?= tnqr_escape($csrf) ?>">
              <input type="hidden" name="action" value="change_pin">
              <div class="field">
                <label for="current-pin">Current PIN</label>
                <input id="current-pin" name="current_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password" required>
              </div>
              <div class="field">
                <label for="new-pin">New PIN</label>
                <input id="new-pin" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>
              </div>
              <div class="field">
                <label for="confirm-pin">Confirm new PIN</label>
                <input id="confirm-pin" name="confirm_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required>
              </div>
              <button class="button button--secondary" type="submit">Change PIN</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>

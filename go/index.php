<?php

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

try {
    $target = tnqr_active_url(tnqr_read_store());
} catch (Throwable $exception) {
    // A damaged or temporarily unavailable data file must never make the printed QR unusable.
    $target = TNQR_HOME_URL;
    error_log('The North QR redirect error: ' . $exception->getMessage());
}

header_remove('X-Powered-By');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
header('Permissions-Policy: camera=(), geolocation=(), microphone=(), payment=(), usb=()');
header('Location: ' . $target, true, 302);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Redirecting&hellip;</title>
</head>
<body>
  <p>Redirecting to <a href="<?= tnqr_escape($target) ?>" rel="noreferrer"><?= tnqr_escape($target) ?></a>&hellip;</p>
</body>
</html>

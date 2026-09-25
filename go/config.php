<?php

declare(strict_types=1);

// Do not expose configuration details when this file is requested directly.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header_remove('X-Powered-By');
    http_response_code(404);
    exit;
}

const TNQR_HOME_URL = 'https://thenorthlatinfestival.ca/';
const TNQR_PUBLIC_URL = 'https://thenorthlatinfestival.ca/go/';
const TNQR_ADMIN_URL = 'https://thenorthlatinfestival.ca/go/admin/';
const TNQR_CANONICAL_HOST = 'thenorthlatinfestival.ca';

const TNQR_PUBLIC_PATH = '/go/';
const TNQR_ADMIN_PATH = '/go/admin/';
const TNQR_QR_ASSET_PATH = '/assets/qr/';

const TNQR_DATA_FILE = __DIR__ . '/data/links.data.php';
const TNQR_RATE_LIMIT_FILE = __DIR__ . '/data/login-rate-limit.data.php';
const TNQR_DATA_PREFIX = "<?php http_response_code(404); exit; ?>\n";

// Initial PIN: 000000. Change it immediately after the production upload.
const TNQR_DEFAULT_PIN_HASH = '$2y$12$641v62lxFX/4MlVvERorHOd0EUT2LOCvw/laixUWVWJzMf3fIOq4W';

const TNQR_SESSION_NAME = 'tnqr_admin_session';
const TNQR_SESSION_TIMEOUT_SECONDS = 1800;
const TNQR_MAX_LINKS = 100;

const TNQR_LOGIN_MAX_FAILURES = 5;
const TNQR_LOGIN_WINDOW_SECONDS = 900;
const TNQR_LOGIN_LOCK_SECONDS = 900;
const TNQR_LOGIN_RATE_MAX_CLIENTS = 250;

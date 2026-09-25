<?php

declare(strict_types=1);

// Do not expose implementation details when this file is requested directly.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header_remove('X-Powered-By');
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

/**
 * @return array{version:int,activeId:string,pinHash:string,links:array<int,array{id:string,label:string,url:string,createdAt:string}>}
 */
function tnqr_default_store(): array
{
    return [
        'version' => 1,
        'activeId' => 'home',
        'pinHash' => TNQR_DEFAULT_PIN_HASH,
        'links' => [
            [
                'id' => 'home',
                'label' => 'Main website',
                'url' => TNQR_HOME_URL,
                'createdAt' => gmdate('c'),
            ],
        ],
    ];
}

/**
 * @param mixed $value
 */
function tnqr_text_length($value): int
{
    $text = (string) $value;
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function tnqr_is_valid_http_url(string $url): bool
{
    if (
        $url === ''
        || strlen($url) > 2048
        || preg_match('/[\x00-\x1F\x7F]/', $url) === 1
        || filter_var($url, FILTER_VALIDATE_URL) === false
    ) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''));

    if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
        return false;
    }

    // Embedded credentials are unnecessary for promotional links and can be misleading.
    return !isset($parts['user']) && !isset($parts['pass']);
}

function tnqr_normalize_url_path(string $path): string
{
    $decoded = rawurldecode($path);
    $segments = preg_split('#/+#', $decoded);
    if (!is_array($segments)) {
        return '/';
    }

    $normalized = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($normalized);
            continue;
        }
        $normalized[] = $segment;
    }

    return '/' . implode('/', $normalized);
}

function tnqr_is_self_redirect(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    $canonicalHost = strtolower(TNQR_CANONICAL_HOST);
    if ($host !== $canonicalHost && $host !== 'www.' . $canonicalHost) {
        return false;
    }

    $path = strtolower(tnqr_normalize_url_path((string) ($parts['path'] ?? '/')));

    // Block the public redirect and every path under /go to prevent redirect loops
    // or accidentally exposing the manager as a promotion destination.
    return $path === '/go' || strpos($path, '/go/') === 0;
}

function tnqr_is_password_hash(string $hash): bool
{
    if ($hash === '' || strlen($hash) > 255) {
        return false;
    }

    $info = password_get_info($hash);
    return isset($info['algoName']) && $info['algoName'] !== 'unknown';
}

/**
 * @param mixed $input
 * @return array{version:int,activeId:string,pinHash:string,links:array<int,array{id:string,label:string,url:string,createdAt:string}>}
 */
function tnqr_normalize_store($input): array
{
    if (!is_array($input)) {
        throw new RuntimeException('The saved link data is not a valid object.');
    }

    $default = tnqr_default_store();
    $links = [];
    $seenIds = [];
    $sourceLinks = isset($input['links']) && is_array($input['links']) ? $input['links'] : [];

    foreach ($sourceLinks as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = trim((string) ($item['id'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $createdAt = trim((string) ($item['createdAt'] ?? ''));

        if (
            $id === ''
            || strlen($id) > 64
            || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1
            || isset($seenIds[$id])
            || $label === ''
            || tnqr_text_length($label) > 80
            || !tnqr_is_valid_http_url($url)
            || tnqr_is_self_redirect($url)
        ) {
            continue;
        }

        $seenIds[$id] = true;
        $links[] = [
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'createdAt' => $createdAt !== '' ? $createdAt : gmdate('c'),
        ];
    }

    if ($links === []) {
        $links = $default['links'];
    }

    $activeId = trim((string) ($input['activeId'] ?? ''));
    $activeExists = false;
    foreach ($links as $link) {
        if ($link['id'] === $activeId) {
            $activeExists = true;
            break;
        }
    }
    if (!$activeExists) {
        $activeId = $links[0]['id'];
    }

    $pinHash = trim((string) ($input['pinHash'] ?? ''));
    if (!tnqr_is_password_hash($pinHash)) {
        throw new RuntimeException('The saved PIN hash is missing or invalid.');
    }

    return [
        'version' => 1,
        'activeId' => $activeId,
        'pinHash' => $pinHash,
        'links' => $links,
    ];
}

/**
 * @return array{version:int,activeId:string,pinHash:string,links:array<int,array{id:string,label:string,url:string,createdAt:string}>}
 */
function tnqr_read_store(): array
{
    if (!is_file(TNQR_DATA_FILE)) {
        return tnqr_default_store();
    }

    $size = filesize(TNQR_DATA_FILE);
    if ($size !== false && $size > 1048576) {
        throw new RuntimeException('The link data file is unexpectedly large.');
    }

    $raw = file_get_contents(TNQR_DATA_FILE);
    if ($raw === false) {
        throw new RuntimeException('The link data file could not be read.');
    }

    if (substr($raw, 0, strlen(TNQR_DATA_PREFIX)) !== TNQR_DATA_PREFIX) {
        throw new RuntimeException('The link data file has an invalid protection header.');
    }

    $raw = substr($raw, strlen(TNQR_DATA_PREFIX));
    $decoded = json_decode($raw, true, 32);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('The link data file contains invalid JSON.');
    }

    return tnqr_normalize_store($decoded);
}

function tnqr_ensure_data_directory(): string
{
    $directory = dirname(TNQR_DATA_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The data directory could not be created.');
    }

    return $directory;
}

/**
 * @param array<string,mixed> $store
 */
function tnqr_write_store(array $store): void
{
    $directory = tnqr_ensure_data_directory();
    $normalized = tnqr_normalize_store($store);
    $json = json_encode(
        $normalized,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        throw new RuntimeException('The link data could not be encoded.');
    }

    $payload = TNQR_DATA_PREFIX . $json . PHP_EOL;
    $lockPath = $directory . '/.links.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('The data lock file could not be opened.');
    }

    @chmod($lockPath, 0600);

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('The link data could not be locked for writing.');
        }

        $temporary = $directory . '/.links-' . bin2hex(random_bytes(8)) . '.tmp';
        $bytes = file_put_contents($temporary, $payload, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($payload)) {
            @unlink($temporary);
            throw new RuntimeException('The link data could not be written. Check the go/data permissions.');
        }

        @chmod($temporary, 0640);
        if (!@rename($temporary, TNQR_DATA_FILE)) {
            @unlink($temporary);
            throw new RuntimeException('The new link data could not replace the old file.');
        }
        @chmod(TNQR_DATA_FILE, 0640);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * @param array{activeId:string,links:array<int,array{id:string,label:string,url:string,createdAt:string}>} $store
 */
function tnqr_active_url(array $store): string
{
    foreach ($store['links'] as $link) {
        if (
            $link['id'] === $store['activeId']
            && tnqr_is_valid_http_url($link['url'])
            && !tnqr_is_self_redirect($link['url'])
        ) {
            return $link['url'];
        }
    }

    return TNQR_HOME_URL;
}

function tnqr_new_id(): string
{
    return bin2hex(random_bytes(8));
}

function tnqr_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tnqr_uses_default_pin(string $pinHash): bool
{
    return password_verify('000000', $pinHash);
}

function tnqr_login_client_key(): string
{
    $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        $address = 'unknown';
    }

    return hash('sha256', $address . '|' . TNQR_SESSION_NAME);
}

/**
 * @return array{version:int,clients:array<string,array{failures:array<int,int>,lockedUntil:int,lastSeen:int}>}
 */
function tnqr_read_rate_limit_state_unlocked(): array
{
    if (!is_file(TNQR_RATE_LIMIT_FILE)) {
        return ['version' => 1, 'clients' => []];
    }

    $raw = file_get_contents(TNQR_RATE_LIMIT_FILE);
    if ($raw === false || substr($raw, 0, strlen(TNQR_DATA_PREFIX)) !== TNQR_DATA_PREFIX) {
        return ['version' => 1, 'clients' => []];
    }

    $decoded = json_decode(substr($raw, strlen(TNQR_DATA_PREFIX)), true, 16);
    if (!is_array($decoded) || !isset($decoded['clients']) || !is_array($decoded['clients'])) {
        return ['version' => 1, 'clients' => []];
    }

    $clients = [];
    foreach ($decoded['clients'] as $key => $record) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/', $key) !== 1 || !is_array($record)) {
            continue;
        }

        $failures = [];
        $sourceFailures = isset($record['failures']) && is_array($record['failures']) ? $record['failures'] : [];
        foreach ($sourceFailures as $timestamp) {
            if (is_int($timestamp) || ctype_digit((string) $timestamp)) {
                $failures[] = (int) $timestamp;
            }
        }

        $clients[$key] = [
            'failures' => $failures,
            'lockedUntil' => max(0, (int) ($record['lockedUntil'] ?? 0)),
            'lastSeen' => max(0, (int) ($record['lastSeen'] ?? 0)),
        ];
    }

    return ['version' => 1, 'clients' => $clients];
}

/**
 * @param array{version:int,clients:array<string,array{failures:array<int,int>,lockedUntil:int,lastSeen:int}>} $state
 */
function tnqr_write_rate_limit_state_unlocked(array $state, string $directory): void
{
    $json = json_encode($state, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('The login rate-limit data could not be encoded.');
    }

    $payload = TNQR_DATA_PREFIX . $json . PHP_EOL;
    $temporary = $directory . '/.login-rate-' . bin2hex(random_bytes(8)) . '.tmp';
    $bytes = file_put_contents($temporary, $payload, LOCK_EX);
    if ($bytes === false || $bytes !== strlen($payload)) {
        @unlink($temporary);
        throw new RuntimeException('The login rate-limit data could not be written.');
    }

    @chmod($temporary, 0640);
    if (!@rename($temporary, TNQR_RATE_LIMIT_FILE)) {
        @unlink($temporary);
        throw new RuntimeException('The login rate-limit data could not be replaced.');
    }
    @chmod(TNQR_RATE_LIMIT_FILE, 0640);
}

/**
 * @template T
 * @param callable(array<string,mixed>&,int):T $callback
 * @return T
 */
function tnqr_update_rate_limit_state(callable $callback)
{
    $directory = tnqr_ensure_data_directory();
    $lockPath = $directory . '/.login-rate.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('The login rate-limit lock could not be opened.');
    }

    @chmod($lockPath, 0600);

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('The login rate-limit data could not be locked.');
        }

        $now = time();
        $state = tnqr_read_rate_limit_state_unlocked();

        foreach ($state['clients'] as $key => $record) {
            $record['failures'] = array_values(array_filter(
                $record['failures'],
                static function (int $timestamp) use ($now): bool {
                    return $timestamp >= $now - TNQR_LOGIN_WINDOW_SECONDS && $timestamp <= $now + 60;
                }
            ));

            if ($record['lockedUntil'] <= $now) {
                $record['lockedUntil'] = 0;
            }

            if (
                $record['lockedUntil'] === 0
                && $record['failures'] === []
                && $record['lastSeen'] < $now - TNQR_LOGIN_WINDOW_SECONDS
            ) {
                unset($state['clients'][$key]);
                continue;
            }

            $state['clients'][$key] = $record;
        }

        $result = $callback($state, $now);

        if (count($state['clients']) > TNQR_LOGIN_RATE_MAX_CLIENTS) {
            uasort(
                $state['clients'],
                static function (array $left, array $right): int {
                    return $right['lastSeen'] <=> $left['lastSeen'];
                }
            );
            $state['clients'] = array_slice($state['clients'], 0, TNQR_LOGIN_RATE_MAX_CLIENTS, true);
        }

        tnqr_write_rate_limit_state_unlocked($state, $directory);
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Atomically reserves one login attempt before the PIN is checked. This caps
 * concurrent guessing as well as sequential guessing.
 *
 * @return array{allowed:bool,lockedUntil:int,remaining:int}
 */
function tnqr_begin_login_attempt(): array
{
    try {
        return tnqr_update_rate_limit_state(
            static function (array &$state, int $now): array {
                $key = tnqr_login_client_key();
                $record = $state['clients'][$key] ?? [
                    'failures' => [],
                    'lockedUntil' => 0,
                    'lastSeen' => $now,
                ];

                if ($record['lockedUntil'] > $now) {
                    $record['lastSeen'] = $now;
                    $state['clients'][$key] = $record;
                    return [
                        'allowed' => false,
                        'lockedUntil' => $record['lockedUntil'],
                        'remaining' => 0,
                    ];
                }

                $record['lockedUntil'] = 0;
                if (count($record['failures']) >= TNQR_LOGIN_MAX_FAILURES) {
                    $record['failures'] = [];
                    $record['lockedUntil'] = $now + TNQR_LOGIN_LOCK_SECONDS;
                    $record['lastSeen'] = $now;
                    $state['clients'][$key] = $record;
                    return [
                        'allowed' => false,
                        'lockedUntil' => $record['lockedUntil'],
                        'remaining' => 0,
                    ];
                }

                $record['failures'][] = $now;
                $record['lastSeen'] = $now;
                $state['clients'][$key] = $record;

                return [
                    'allowed' => true,
                    'lockedUntil' => 0,
                    'remaining' => max(0, TNQR_LOGIN_MAX_FAILURES - count($record['failures'])),
                ];
            }
        );
    } catch (Throwable $exception) {
        error_log('The North QR login rate-limit reservation error: ' . $exception->getMessage());
        return [
            'allowed' => true,
            'lockedUntil' => 0,
            'remaining' => TNQR_LOGIN_MAX_FAILURES,
        ];
    }
}

/**
 * Keeps a failed reserved attempt and starts the lock when the limit is reached.
 *
 * @return array{lockedUntil:int,remaining:int}
 */
function tnqr_finalize_login_failure(): array
{
    try {
        return tnqr_update_rate_limit_state(
            static function (array &$state, int $now): array {
                $key = tnqr_login_client_key();
                $record = $state['clients'][$key] ?? [
                    'failures' => [$now],
                    'lockedUntil' => 0,
                    'lastSeen' => $now,
                ];

                if ($record['lockedUntil'] <= $now && count($record['failures']) >= TNQR_LOGIN_MAX_FAILURES) {
                    $record['failures'] = [];
                    $record['lockedUntil'] = $now + TNQR_LOGIN_LOCK_SECONDS;
                }

                $record['lastSeen'] = $now;
                $state['clients'][$key] = $record;

                return [
                    'lockedUntil' => $record['lockedUntil'],
                    'remaining' => max(0, TNQR_LOGIN_MAX_FAILURES - count($record['failures'])),
                ];
            }
        );
    } catch (Throwable $exception) {
        error_log('The North QR login rate-limit failure update error: ' . $exception->getMessage());
        return ['lockedUntil' => 0, 'remaining' => TNQR_LOGIN_MAX_FAILURES];
    }
}

function tnqr_clear_login_failures(): void
{
    try {
        tnqr_update_rate_limit_state(
            static function (array &$state, int $now): bool {
                unset($state['clients'][tnqr_login_client_key()]);
                return true;
            }
        );
    } catch (Throwable $exception) {
        error_log('The North QR login rate-limit clear error: ' . $exception->getMessage());
    }
}

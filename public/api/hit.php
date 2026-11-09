<?php
// Cookie-free visit counter for the admin panel. Stores daily totals per page and per referring site.
// Unique visitors are counted with a hash of IP + browser and a salt that changes every day,
// so a visitor cannot be followed across days and no IP address is stored.
declare(strict_types=1);
require __DIR__ . '/_lib/club32.php';

http_response_code(204);
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !same_origin()) exit;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|headless|lighthouse|curl|wget|python|http-client/i', $ua)) exit;
if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') exit;

$in = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$path = (string)($in['p'] ?? '/');
if (!preg_match('#^/[a-z0-9/_-]{0,80}$#i', $path) || str_starts_with($path, '/admin')) exit;
$path = rtrim($path, '/') . '/';

$ref = strtolower((string)(parse_url((string)($in['r'] ?? ''), PHP_URL_HOST) ?? ''));
$ref = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $ref);
if (str_contains($ref, 'lewissmartracing.com') || $ref === 'localhost') $ref = '';

try {
    $db = db();
    $day = date('Y-m-d');
    $salt = setting("salt_$day");
    if ($salt === '') {
        $salt = bin2hex(random_bytes(16));
        $db->exec("DELETE FROM settings WHERE key LIKE 'salt_%'");
        set_setting("salt_$day", $salt);
    }
    $h = substr(hash('sha256', $salt . client_ip() . $ua), 0, 20);
    $db->prepare('INSERT INTO pv (day, path, n) VALUES (?, ?, 1) ON CONFLICT(day, path) DO UPDATE SET n = n + 1')->execute([$day, $path]);
    $db->prepare('INSERT OR IGNORE INTO uv (day, h) VALUES (?, ?)')->execute([$day, $h]);
    if ($ref !== '') $db->prepare('INSERT INTO refs (day, host, n) VALUES (?, ?, 1) ON CONFLICT(day, host) DO UPDATE SET n = n + 1')->execute([$day, mb_substr($ref, 0, 80)]);
} catch (Throwable $e) {
    error_log('hit: ' . $e->getMessage());
}

<?php
// Enquiry form handler: stores the enquirer as a BlueFox contact and emails
// the enquiry to Lewis through a BlueFox transactional email.
// The API key lives on the server in /etc/lewissmartracing/bluefox.env, never in the repo.
declare(strict_types=1);

const ENV_FILE = '/etc/lewissmartracing/bluefox.env';
const API = 'https://api.bluefox.email/v1';
const THANK_YOU = '/thank-you/';
const ALLOWED_HOSTS = ['lewissmartracing.com', 'www.lewissmartracing.com', 'localhost:4321'];
const TOPICS = ['general' => 'General enquiry', 'sponsorship' => 'Sponsorship', 'media' => 'Media'];
const RATE_LIMIT = 5;        // submissions
const RATE_WINDOW = 3600;    // per hour, per IP

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function done(int $code, string $error = ''): never {
    global $wantsJson;
    http_response_code($code);
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode($code < 300 ? ['ok' => true, 'redirect' => THANK_YOU] : ['ok' => false, 'error' => $error]);
    } elseif ($code < 300 || $code === 303) {
        header('Location: ' . THANK_YOU, true, 303);
    } else {
        header('Location: /contact/?error=' . rawurlencode($error), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); done(405, 'method'); }

// Same-site check: browsers send Origin on POST.
$origin = parse_url($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';
$port = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_PORT);
if (!in_array($port ? "$origin:$port" : $origin, ALLOWED_HOSTS, true)) done(403, 'origin');

$in = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    ? (json_decode(file_get_contents('php://input') ?: '', true) ?: [])
    : $_POST;
$f = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

// Honeypot: real people never fill the hidden "website" field. Pretend success.
if ($f('website', 200) !== '') done(200);

$name = $f('name', 120);
$email = $f('email', 200);
$company = $f('company', 160);
$topic = array_key_exists($f('topic', 20), TOPICS) ? $f('topic', 20) : 'general';
$message = $f('message', 5000);
if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) done(422, 'invalid');

// Simple per-IP rate limit.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/lsr-enquiry-' . hash('sha256', $ip);
$hits = array_filter(json_decode(@file_get_contents($rlFile) ?: '[]', true) ?: [], fn($t) => $t > time() - RATE_WINDOW);
if (count($hits) >= RATE_LIMIT) done(429, 'rate');
$hits[] = time();
@file_put_contents($rlFile, json_encode(array_values($hits)), LOCK_EX);

$env = @parse_ini_file(ENV_FILE) ?: [];
$key = $env['BLUEFOX_API_KEY'] ?? '';
$project = $env['BLUEFOX_PROJECT_ID'] ?? '';
$notifyId = $env['BLUEFOX_NOTIFY_EMAIL_ID'] ?? '';
$notifyTo = $env['BLUEFOX_NOTIFY_TO'] ?? '';
if ($key === '' || $project === '' || $notifyId === '' || $notifyTo === '') { error_log('enquiry: BlueFox env incomplete'); done(503, 'config'); }

function bluefox(string $path, array $body, string $key): array {
    $ch = curl_init(API . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $key"],
        CURLOPT_POSTFIELDS => json_encode($body),
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, is_string($res) ? $res : ''];
}

$topicLabel = TOPICS[$topic];
$when = (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('j M Y, H:i');

// 1. The enquiry itself goes to Lewis. This is the part that must work.
[$code, $res] = bluefox("/projectId/$project/send-transactional", [
    'email' => $notifyTo,
    'transactionalId' => $notifyId,
    'data' => [
        'name' => $name, 'email' => $email, 'company' => $company !== '' ? $company : '-',
        'topic' => $topicLabel, 'message' => $message, 'submittedAt' => $when,
    ],
], $key);
if ($code < 200 || $code >= 300) { error_log("enquiry: send-transactional HTTP $code $res"); done(502, 'send'); }

// 2. Keep the enquirer as a contact in BlueFox (best effort; no marketing list, no opt-in).
[$code, $res] = bluefox("/projectId/$project/contacts", [
    'email' => $email,
    'name' => $name,
    'tags' => ['website-enquiry', "enquiry-$topic"],
], $key);
if ($code >= 300) error_log("enquiry: contact upsert HTTP $code $res");

done(200);

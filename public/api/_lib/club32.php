<?php
// Shared code for Club32 (number raffle), the admin panel and the visit counter.
// Data: one SQLite file outside the web root (the deploy rsync --delete would wipe it otherwise).
// Secrets: /etc/lewissmartracing/*.env, never in the repo.
// Local development: set LSR_ETC and LSR_DATA to folders of your own.
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

date_default_timezone_set('Europe/London');

const C32_ALLOWED_HOSTS = ['lewissmartracing.com', 'www.lewissmartracing.com', 'localhost:4321', 'localhost:8080'];
const C32_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const C32_SITE = 'https://lewissmartracing.com';

// ---------------------------------------------------------------- config

function cfg(string $key, string $default = ''): string {
    static $c = null;
    if ($c === null) {
        $dir = getenv('LSR_ETC') ?: '/etc/lewissmartracing';
        $c = array_merge(@parse_ini_file("$dir/bluefox.env") ?: [], @parse_ini_file("$dir/club32.env") ?: []);
    }
    return (string)($c[$key] ?? $default);
}

function site_url(): string { return rtrim(cfg('SITE_URL', C32_SITE), '/'); }

// ---------------------------------------------------------------- database

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = getenv('LSR_DATA') ?: cfg('DATA_DIR', '/var/lib/lewissmartracing');
    $pdo = new PDO("sqlite:$dir/club32.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $db): void {
    $v = (int)$db->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 1) return;
    $db->exec(<<<SQL
    BEGIN;
    CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
    CREATE TABLE orders (
        id INTEGER PRIMARY KEY,
        code TEXT NOT NULL UNIQUE,
        season INTEGER NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT '',
        amount_pence INTEGER NOT NULL,
        status TEXT NOT NULL CHECK (status IN ('pending','paid','expired','cancelled')),
        source TEXT NOT NULL DEFAULT 'web',
        note TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL,
        expires_at INTEGER,
        reminded_at INTEGER,
        paid_at INTEGER,
        closed_at INTEGER,
        handled_by TEXT,
        ip_hash TEXT
    );
    CREATE INDEX orders_status ON orders (season, status);
    CREATE INDEX orders_email ON orders (email);
    -- Every number an order asked for (history, never deleted).
    CREATE TABLE order_numbers (
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        number INTEGER NOT NULL,
        PRIMARY KEY (order_id, number)
    );
    -- The live lock: a number has a row here while its order is pending or paid.
    -- The primary key makes double selling impossible.
    CREATE TABLE claims (
        season INTEGER NOT NULL,
        number INTEGER NOT NULL CHECK (number BETWEEN 1 AND 100),
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        PRIMARY KEY (season, number)
    );
    -- Last season's members: first refusal on their old number until presale_until.
    CREATE TABLE presale (
        token TEXT PRIMARY KEY,
        season INTEGER NOT NULL,
        number INTEGER NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL,
        invited_at INTEGER,
        UNIQUE (season, number)
    );
    CREATE TABLE draws (
        id INTEGER PRIMARY KEY,
        season INTEGER NOT NULL,
        label TEXT NOT NULL,
        method TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        created_by TEXT
    );
    CREATE TABLE winners (
        draw_id INTEGER NOT NULL REFERENCES draws(id) ON DELETE CASCADE,
        place INTEGER NOT NULL,
        number INTEGER NOT NULL,
        order_id INTEGER REFERENCES orders(id),
        prize TEXT NOT NULL,
        notified_at INTEGER,
        PRIMARY KEY (draw_id, place)
    );
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL UNIQUE,
        pass_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        last_login INTEGER
    );
    CREATE TABLE audit (id INTEGER PRIMARY KEY, at INTEGER NOT NULL, user TEXT, action TEXT NOT NULL, detail TEXT NOT NULL DEFAULT '');
    CREATE TABLE hits (key TEXT NOT NULL, at INTEGER NOT NULL);
    CREATE INDEX hits_key ON hits (key, at);
    -- Visit counter: daily totals only, no cookies, no IP addresses.
    CREATE TABLE pv (day TEXT NOT NULL, path TEXT NOT NULL, n INTEGER NOT NULL, PRIMARY KEY (day, path));
    CREATE TABLE uv (day TEXT NOT NULL, h TEXT NOT NULL, PRIMARY KEY (day, h));
    CREATE TABLE refs (day TEXT NOT NULL, host TEXT NOT NULL, n INTEGER NOT NULL, PRIMARY KEY (day, host));
    PRAGMA user_version = 1;
    COMMIT;
    SQL);
}

const SETTING_DEFAULTS = [
    'season' => '2027',
    'price_pence' => '3200',
    'hold_hours' => '24',
    'reminder_hours' => '6',       // reminder this many hours before the hold runs out
    'max_per_order' => '10',
    'sales_open' => '0',           // 0 = only last season's members (presale links) can reserve
    'presale_until' => '',         // 'YYYY-MM-DD HH:MM' London time; old numbers locked for their owners until then
    'bank_name' => '',
    'bank_sort' => '',
    'bank_account' => '',
    'prize1' => 'Two tickets to the upcoming BSB race weekend',
    'prize2' => 'A custom smart32xugly&co cap',
];

function setting(string $key): string {
    $v = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $v->execute([$key]);
    $r = $v->fetchColumn();
    return $r === false ? (SETTING_DEFAULTS[$key] ?? '') : (string)$r;
}

function set_setting(string $key, string $value): void {
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
}

function season(): int { return (int)setting('season'); }

function presale_until(): ?int {
    $s = setting('presale_until');
    if ($s === '') return null;
    $t = strtotime($s);
    return $t === false ? null : $t;
}

function presale_active(): bool { $t = presale_until(); return $t !== null && time() < $t; }

function audit(?string $user, string $action, string $detail = ''): void {
    db()->prepare('INSERT INTO audit (at, user, action, detail) VALUES (?, ?, ?, ?)')->execute([time(), $user, $action, $detail]);
}

// ---------------------------------------------------------------- helpers

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }
function ip_hash(): string { return substr(hash('sha256', client_ip() . '|' . cfg('BLUEFOX_PROJECT_ID', 'lsr')), 0, 24); }

/** Sliding-window limiter. Returns false when the key is over its limit. */
function rate_ok(string $key, int $limit, int $window): bool {
    $db = db();
    $db->prepare('DELETE FROM hits WHERE at < ?')->execute([time() - 86400 * 2]);
    $q = $db->prepare('SELECT COUNT(*) FROM hits WHERE key = ? AND at > ?');
    $q->execute([$key, time() - $window]);
    if ((int)$q->fetchColumn() >= $limit) return false;
    $db->prepare('INSERT INTO hits (key, at) VALUES (?, ?)')->execute([$key, time()]);
    return true;
}

function new_code(): string {
    $a = C32_CODE_ALPHABET; $s = '';
    for ($i = 0; $i < 5; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return "C32-$s";
}

/** "george" or "GEORGE" becomes "George"; names typed with their own capitals ("McDonald") are left alone. */
function tidy_name(string $s): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return ($s === mb_strtolower($s) || $s === mb_strtoupper($s)) ? mb_convert_case($s, MB_CASE_TITLE) : $s;
}

function money(int $pence): string { return '£' . number_format($pence / 100, 2); }
function when(?int $t): string { return $t ? date('D j M, H:i', $t) : ''; }
function nums_label(array $nums): string { sort($nums); return implode(', ', array_map(fn($n) => "#$n", $nums)); }

function same_origin(): bool {
    $src = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($src, PHP_URL_HOST) ?? '';
    $port = parse_url($src, PHP_URL_PORT);
    return in_array($port ? "$host:$port" : $host, C32_ALLOWED_HOSTS, true);
}

function json_out(int $code, array $body): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_input(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        return json_decode(file_get_contents('php://input') ?: '', true) ?: [];
    }
    return $_POST;
}

function clean(array $in, string $k, int $max): string { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); }

// ---------------------------------------------------------------- BlueFox

function bluefox(string $path, array $body): array {
    $key = cfg('BLUEFOX_API_KEY');
    $ch = curl_init('https://api.bluefox.email/v1' . $path);
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

/**
 * Sends one of the Club32 transactional emails. $kind maps to BLUEFOX_C32_<KIND>_ID in the env file.
 * With LSR_MAIL_LOG set (local development) the email is written to that file instead.
 */
function send_mail(string $kind, string $to, array $data): bool {
    $data += ['season' => (string)season(), 'site' => site_url()];
    if ($log = getenv('LSR_MAIL_LOG')) {
        file_put_contents($log, json_encode(['at' => date('c'), 'kind' => $kind, 'to' => $to, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
        return true;
    }
    $id = cfg('BLUEFOX_C32_' . strtoupper($kind) . '_ID');
    $project = cfg('BLUEFOX_PROJECT_ID');
    if ($id === '' || $project === '' || cfg('BLUEFOX_API_KEY') === '') { error_log("club32: no BlueFox id for $kind"); return false; }
    [$code, $res] = bluefox("/projectId/$project/send-transactional", ['email' => $to, 'transactionalId' => $id, 'data' => $data]);
    if ($code < 200 || $code >= 300) { error_log("club32: send $kind HTTP $code $res"); return false; }
    return true;
}

function upsert_contact(string $email, string $name, array $tags): void {
    if (getenv('LSR_MAIL_LOG') || cfg('BLUEFOX_API_KEY') === '') return;
    [$code, $res] = bluefox('/projectId/' . cfg('BLUEFOX_PROJECT_ID') . '/contacts', ['email' => $email, 'name' => $name, 'tags' => $tags]);
    if ($code >= 300) error_log("club32: contact upsert HTTP $code $res");
}

// ---------------------------------------------------------------- orders

function order_by_code(string $code): ?array {
    $q = db()->prepare('SELECT * FROM orders WHERE code = ?');
    $q->execute([strtoupper(trim($code))]);
    $o = $q->fetch();
    return $o ?: null;
}

function order_by_id(int $id): ?array {
    $q = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $q->execute([$id]);
    $o = $q->fetch();
    return $o ?: null;
}

function order_numbers(int $orderId): array {
    $q = db()->prepare('SELECT number FROM order_numbers WHERE order_id = ? ORDER BY number');
    $q->execute([$orderId]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

function bank_details(): array {
    return ['name' => setting('bank_name'), 'sortCode' => setting('bank_sort'), 'account' => setting('bank_account')];
}

/** Template data shared by every order email. */
function order_mail_data(array $o): array {
    $nums = order_numbers((int)$o['id']);
    $bank = bank_details();
    return [
        'firstName' => $o['first_name'],
        'lastName' => $o['last_name'],
        'code' => $o['code'],
        'numbers' => nums_label($nums),
        'count' => (string)count($nums),
        'amount' => money((int)$o['amount_pence']),
        'expiresAt' => when($o['expires_at'] ? (int)$o['expires_at'] : null),
        'bankName' => $bank['name'],
        'sortCode' => $bank['sortCode'],
        'accountNumber' => $bank['account'],
        'orderUrl' => site_url() . '/club32/reserved/?code=' . rawurlencode($o['code']),
    ];
}

/**
 * Locks the numbers for a new order. Throws RuntimeException('taken:13,27') when any is gone.
 * Caller must run inside a transaction.
 */
function claim_numbers(int $orderId, array $nums): void {
    $db = db();
    $season = season();
    $taken = [];
    $check = $db->prepare('SELECT 1 FROM claims WHERE season = ? AND number = ?');
    foreach ($nums as $n) { $check->execute([$season, $n]); if ($check->fetchColumn()) $taken[] = $n; }
    if ($taken) throw new RuntimeException('taken:' . implode(',', $taken));
    $ins = $db->prepare('INSERT INTO claims (season, number, order_id) VALUES (?, ?, ?)');
    foreach ($nums as $n) $ins->execute([$season, $n, $orderId]);
}

function release_numbers(int $orderId): void {
    db()->prepare('DELETE FROM claims WHERE order_id = ?')->execute([$orderId]);
}

/** Creates a pending (web, presale) or paid (manual) order. Returns the order row. */
function create_order(array $p): array {
    $db = db();
    $nums = array_values(array_unique(array_map('intval', $p['numbers'])));
    sort($nums);
    $status = $p['status'] ?? 'pending';
    $now = time();
    $db->beginTransaction();
    try {
        do { $code = new_code(); } while (order_by_code($code));
        $db->prepare('INSERT INTO orders (code, season, first_name, last_name, email, phone, amount_pence, status, source, note, created_at, expires_at, paid_at, handled_by, ip_hash)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([
               $code, season(), tidy_name($p['first_name']), tidy_name($p['last_name']), strtolower($p['email']), $p['phone'] ?? '',
               count($nums) * (int)setting('price_pence'), $status, $p['source'] ?? 'web', $p['note'] ?? '', $now,
               $status === 'pending' ? $now + (int)setting('hold_hours') * 3600 : null,
               $status === 'paid' ? $now : null, $p['handled_by'] ?? null, $p['ip_hash'] ?? null,
           ]);
        $id = (int)$db->lastInsertId();
        $ins = $db->prepare('INSERT INTO order_numbers (order_id, number) VALUES (?, ?)');
        foreach ($nums as $n) $ins->execute([$id, $n]);
        claim_numbers($id, $nums);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return order_by_id($id);
}

/** Marks an order paid. Works on expired orders too, as long as the numbers are still free. */
function approve_order(int $id, string $by): array {
    $db = db();
    $o = order_by_id($id);
    if (!$o || !in_array($o['status'], ['pending', 'expired'], true)) throw new RuntimeException('state');
    $db->beginTransaction();
    try {
        if ($o['status'] === 'expired') claim_numbers($id, order_numbers($id));
        $db->prepare("UPDATE orders SET status = 'paid', paid_at = ?, closed_at = NULL, handled_by = ? WHERE id = ?")->execute([time(), $by, $id]);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    $o = order_by_id($id);
    send_mail('confirmed', $o['email'], order_mail_data($o));
    upsert_contact($o['email'], $o['first_name'] . ' ' . $o['last_name'], ['club32', 'club32-' . $o['season']]);
    audit($by, 'approve', $o['code']);
    return $o;
}

function cancel_order(int $id, string $by, bool $notify): void {
    $db = db();
    $o = order_by_id($id);
    if (!$o || $o['status'] === 'cancelled') return;
    $db->beginTransaction();
    release_numbers($id);
    $db->prepare("UPDATE orders SET status = 'cancelled', closed_at = ?, handled_by = ? WHERE id = ?")->execute([time(), $by, $id]);
    $db->commit();
    if ($notify) send_mail('cancelled', $o['email'], order_mail_data($o));
    audit($by, 'cancel', $o['code']);
}

function extend_order(int $id, string $by): void {
    $o = order_by_id($id);
    if (!$o || $o['status'] !== 'pending') return;
    $base = max(time(), (int)$o['expires_at']);
    db()->prepare('UPDATE orders SET expires_at = ?, reminded_at = NULL WHERE id = ?')->execute([$base + (int)setting('hold_hours') * 3600, $id]);
    audit($by, 'extend', $o['code']);
}

/**
 * Housekeeping: reminders before a hold runs out, and releasing expired holds.
 * Runs on public requests (at most once a minute) and from cron.
 */
function sweep(bool $force = false): void {
    $db = db();
    if (!$force && (int)setting('last_sweep') > time() - 60) return;
    set_setting('last_sweep', (string)time());
    $now = time();

    $q = $db->prepare("SELECT * FROM orders WHERE status = 'pending' AND reminded_at IS NULL AND expires_at > ? AND expires_at <= ?");
    $q->execute([$now, $now + (int)setting('reminder_hours') * 3600]);
    foreach ($q->fetchAll() as $o) {
        $db->prepare('UPDATE orders SET reminded_at = ? WHERE id = ?')->execute([$now, $o['id']]);
        send_mail('reminder', $o['email'], order_mail_data($o));
    }

    $q = $db->prepare("SELECT * FROM orders WHERE status = 'pending' AND expires_at <= ?");
    $q->execute([$now]);
    foreach ($q->fetchAll() as $o) {
        $db->beginTransaction();
        release_numbers((int)$o['id']);
        $db->prepare("UPDATE orders SET status = 'expired', closed_at = ? WHERE id = ?")->execute([$now, $o['id']]);
        $db->commit();
        send_mail('expired', $o['email'], order_mail_data($o));
        audit(null, 'expire', $o['code']);
    }
}

// ---------------------------------------------------------------- public board

function public_first_name(string $first): string {
    $f = trim(preg_split('/\s+/', trim($first))[0] ?? '');
    return mb_convert_case(mb_substr($f, 0, 20), MB_CASE_TITLE);
}

/** Status of all 100 numbers: free | held (awaiting payment) | member (presale) | taken. */
function board(): array {
    $db = db();
    $season = season();
    $out = [];
    for ($n = 1; $n <= 100; $n++) $out[$n] = ['n' => $n, 's' => 'free'];
    if (presale_active()) {
        $q = $db->prepare('SELECT number FROM presale WHERE season = ?');
        $q->execute([$season]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $n) $out[(int)$n]['s'] = 'member';
    }
    $q = $db->prepare('SELECT c.number, o.status, o.first_name FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = ?');
    $q->execute([$season]);
    foreach ($q->fetchAll() as $r) {
        $n = (int)$r['number'];
        $out[$n] = $r['status'] === 'paid'
            ? ['n' => $n, 's' => 'taken', 'name' => public_first_name($r['first_name'])]
            : ['n' => $n, 's' => 'held'];
    }
    return array_values($out);
}

function public_winners(): array {
    $q = db()->prepare('SELECT d.id, d.label, d.created_at, w.place, w.number, w.prize, o.first_name
                        FROM draws d JOIN winners w ON w.draw_id = d.id LEFT JOIN orders o ON o.id = w.order_id
                        WHERE d.season = ? ORDER BY d.created_at DESC, w.place');
    $q->execute([season()]);
    $draws = [];
    foreach ($q->fetchAll() as $r) {
        $id = (int)$r['id'];
        $draws[$id] ??= ['label' => $r['label'], 'date' => date('j M Y', (int)$r['created_at']), 'winners' => []];
        $draws[$id]['winners'][] = ['place' => (int)$r['place'], 'number' => (int)$r['number'], 'prize' => $r['prize'], 'name' => $r['first_name'] ? public_first_name($r['first_name']) : ''];
    }
    return array_values($draws);
}

function presale_by_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $q = db()->prepare('SELECT * FROM presale WHERE token = ? AND season = ?');
    $q->execute([$token, season()]);
    $r = $q->fetch();
    return $r ?: null;
}

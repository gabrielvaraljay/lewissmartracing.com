<?php
// POST: reserve Club32 numbers for hold_hours while the buyer pays by bank transfer.
// Body (JSON or form): numbers[], first_name, last_name, email, phone?, consent, claim? (presale token), website (honeypot).
declare(strict_types=1);
require __DIR__ . '/../_lib/club32.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); json_out(405, ['ok' => false, 'error' => 'method']); }
if (!same_origin()) json_out(403, ['ok' => false, 'error' => 'origin']);

$in = json_input();
if (clean($in, 'website', 200) !== '') json_out(200, ['ok' => true, 'code' => 'C32-OK']); // honeypot

$first = clean($in, 'first_name', 60);
$last = clean($in, 'last_name', 60);
$email = strtolower(clean($in, 'email', 200));
$phone = clean($in, 'phone', 30);
$raw = $in['numbers'] ?? [];
if (is_string($raw)) $raw = explode(',', $raw);
$nums = array_values(array_unique(array_filter(array_map('intval', (array)$raw), fn($n) => $n >= 1 && $n <= 100)));

if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$nums || empty($in['consent'])) {
    json_out(422, ['ok' => false, 'error' => 'invalid']);
}

try {
    sweep();
    $max = (int)setting('max_per_order');
    if (count($nums) > $max) json_out(422, ['ok' => false, 'error' => 'too_many', 'max' => $max]);

    // Presale: a returning member's link unlocks their own number, even before general sales open.
    $presale = isset($in['claim']) ? presale_by_token((string)$in['claim']) : null;
    $salesOpen = setting('sales_open') === '1';
    if (!$salesOpen && !$presale) json_out(409, ['ok' => false, 'error' => 'closed']);
    if (!$salesOpen && $presale && $nums !== [(int)$presale['number']]) json_out(409, ['ok' => false, 'error' => 'closed']);

    // Numbers held back for last season's members during the presale.
    if (presale_active()) {
        $q = db()->prepare('SELECT number FROM presale WHERE season = ?');
        $q->execute([season()]);
        $locked = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        if ($presale) $locked = array_diff($locked, [(int)$presale['number']]);
        $blocked = array_values(array_intersect($nums, $locked));
        if ($blocked) json_out(409, ['ok' => false, 'error' => 'taken', 'numbers' => $blocked]);
    }

    // Abuse limits: per connection, and open holds per email.
    if (!rate_ok('reserve:' . ip_hash(), 6, 3600)) json_out(429, ['ok' => false, 'error' => 'rate']);
    $q = db()->prepare("SELECT COUNT(*) FROM orders WHERE email = ? AND status = 'pending'");
    $q->execute([$email]);
    if ((int)$q->fetchColumn() >= 2) json_out(429, ['ok' => false, 'error' => 'pending']);

    try {
        $o = create_order([
            'numbers' => $nums, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone,
            'source' => $presale ? 'presale' : 'web', 'ip_hash' => ip_hash(),
        ]);
    } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'taken:')) {
            json_out(409, ['ok' => false, 'error' => 'taken', 'numbers' => array_map('intval', explode(',', substr($e->getMessage(), 6)))]);
        }
        throw $e;
    }

    $data = order_mail_data($o);
    send_mail('reserved', $o['email'], $data);
    if ($to = cfg('BLUEFOX_NOTIFY_TO')) {
        send_mail('admin', $to, $data + ['email' => $o['email'], 'phone' => $o['phone'] ?: '-', 'adminUrl' => site_url() . '/admin/?p=orders']);
    }
    json_out(200, ['ok' => true, 'code' => $o['code'], 'redirect' => '/club32/reserved/?code=' . rawurlencode($o['code'])]);
} catch (Throwable $e) {
    error_log('club32 reserve: ' . $e->getMessage());
    json_out(503, ['ok' => false, 'error' => 'unavailable']);
}

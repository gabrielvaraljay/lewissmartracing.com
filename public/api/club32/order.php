<?php
// GET ?code=C32-XXXXX: what the buyer needs to pay (numbers, amount, bank details, deadline, status).
// Only the first name is returned; the code itself is the buyer's reference.
declare(strict_types=1);
require __DIR__ . '/../_lib/club32.php';

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if (!preg_match('/^C32-[A-Z0-9]{5}$/', $code)) json_out(404, ['ok' => false, 'error' => 'not_found']);

try {
    if (!rate_ok('order:' . ip_hash(), 60, 3600)) json_out(429, ['ok' => false, 'error' => 'rate']);
    sweep();
    $o = order_by_code($code);
    if (!$o) json_out(404, ['ok' => false, 'error' => 'not_found']);
    json_out(200, [
        'ok' => true,
        'code' => $o['code'],
        'status' => $o['status'],
        'firstName' => public_first_name($o['first_name']),
        'numbers' => order_numbers((int)$o['id']),
        'amount' => money((int)$o['amount_pence']),
        'expiresAt' => $o['expires_at'] ? date('c', (int)$o['expires_at']) : null,
        'bank' => $o['status'] === 'pending' ? bank_details() : null,
    ]);
} catch (Throwable $e) {
    error_log('club32 order: ' . $e->getMessage());
    json_out(503, ['ok' => false, 'error' => 'unavailable']);
}

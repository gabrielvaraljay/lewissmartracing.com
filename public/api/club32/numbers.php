<?php
// GET: the Club32 board (status of numbers 1-100), sale state, prizes and past winners.
// Optional ?claim=<token>: a returning member's presale link, answers with their number.
declare(strict_types=1);
require __DIR__ . '/../_lib/club32.php';

try {
    sweep();
    $claim = null;
    if (isset($_GET['claim']) && ($p = presale_by_token((string)$_GET['claim']))) {
        $claim = presale_active() || setting('sales_open') === '0'
            ? ['number' => (int)$p['number'], 'firstName' => $p['first_name'], 'lastName' => $p['last_name'], 'email' => $p['email']]
            : ['expired' => true];
    }
    $until = presale_until();
    json_out(200, [
        'ok' => true,
        'season' => season(),
        'pricePence' => (int)setting('price_pence'),
        'holdHours' => (int)setting('hold_hours'),
        'maxPerOrder' => (int)setting('max_per_order'),
        'salesOpen' => setting('sales_open') === '1',
        'presaleUntil' => $until && $until > time() ? date('c', $until) : null,
        'prizes' => [setting('prize1'), setting('prize2')],
        'numbers' => board(),
        'draws' => public_winners(),
        'claim' => $claim,
    ]);
} catch (Throwable $e) {
    error_log('club32 numbers: ' . $e->getMessage());
    json_out(503, ['ok' => false, 'error' => 'unavailable']);
}

<?php
// Lewis Smart Racing admin: Club32 reservations, members, presale, draws, visitor stats and settings.
// Not linked from the site, never indexed, and every page needs a login.
// Users are created on the server with: php /var/www/lewissmartracing.com/admin/cli.php add-user <name>
declare(strict_types=1);
require __DIR__ . '/../api/_lib/club32.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
session_name('lsr_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function me(): ?string { return $_SESSION['user'] ?? null; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function flash(string $msg, string $kind = 'ok'): void { $_SESSION['flash'][] = [$kind, $msg]; }
function go(string $query = ''): never { header('Location: /admin/' . ($query ? "?$query" : ''), true, 303); exit; }
function url(array $q): string { return '/admin/?' . http_build_query($q); }

// ---------------------------------------------------------------- login

$p = (string)($_GET['p'] ?? 'dash');

if ($p === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/', true, 303);
    exit;
}

if (!me()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $key = 'login:' . ip_hash();
        $q = db()->prepare('SELECT COUNT(*) FROM hits WHERE key = ? AND at > ?');
        $q->execute([$key, time() - 900]);
        if ((int)$q->fetchColumn() >= 5) {
            $error = 'Too many attempts. Try again in 15 minutes.';
        } else {
            $q = db()->prepare('SELECT * FROM users WHERE username = ?');
            $q->execute([strtolower(trim((string)($_POST['username'] ?? '')))]);
            $u = $q->fetch();
            if ($u && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = $u['username'];
                db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([time(), $u['id']]);
                audit($u['username'], 'login');
                go();
            }
            db()->prepare('INSERT INTO hits (key, at) VALUES (?, ?)')->execute([$key, time()]);
            usleep(400000);
            $error = 'Wrong username or password.';
        }
    }
    layout_start('Sign in', false);
    echo '<form method="post" class="box login"><h1>Lewis Smart #32 admin</h1>';
    if ($error) echo '<p class="flash flash--err">' . h($error) . '</p>';
    echo '<label>Username<input name="username" autocomplete="username" required autofocus></label>
          <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
          <button class="btn">Sign in</button></form>';
    layout_end();
    exit;
}

// ---------------------------------------------------------------- actions (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Session expired. Go back, reload the page and try again.'); }
    $_POST += array_fill_keys(['phone', 'note', 'numbers', 'first_name', 'last_name', 'email', 'csv', 'label', 'old', 'new'], '');
    $a = (string)($_POST['a'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $back = (string)($_POST['back'] ?? '');
    try {
        switch ($a) {
            case 'approve':
                $o = approve_order($id, me());
                flash("{$o['code']} marked as paid. {$o['first_name']} has been emailed.");
                break;
            case 'extend':
                extend_order($id, me());
                flash('Hold extended by ' . setting('hold_hours') . ' hours.');
                break;
            case 'cancel':
                cancel_order($id, me(), !empty($_POST['notify']));
                flash('Reservation cancelled, numbers released.');
                break;
            case 'resend':
                $o = order_by_id($id);
                if ($o) {
                    $kind = $o['status'] === 'paid' ? 'confirmed' : 'reserved';
                    send_mail($kind, $o['email'], order_mail_data($o)) ? flash('Email sent again.') : flash('The email could not be sent.', 'err');
                }
                break;
            case 'note':
                db()->prepare('UPDATE orders SET note = ? WHERE id = ?')->execute([mb_substr(trim((string)$_POST['note']), 0, 500), $id]);
                flash('Note saved.');
                break;
            case 'manual':
                $nums = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string)$_POST['numbers'])), fn($n) => $n >= 1 && $n <= 100));
                $email = strtolower(trim((string)$_POST['email']));
                if (!$nums || trim((string)$_POST['first_name']) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Numbers, first name and a valid email are needed.', 'err'); go('p=add'); }
                $paid = ($_POST['status'] ?? '') === 'paid';
                $o = create_order([
                    'numbers' => $nums, 'first_name' => trim((string)$_POST['first_name']), 'last_name' => trim((string)$_POST['last_name']),
                    'email' => $email, 'phone' => trim((string)$_POST['phone']), 'status' => $paid ? 'paid' : 'pending',
                    'source' => 'manual', 'note' => trim((string)$_POST['note']), 'handled_by' => me(),
                ]);
                audit(me(), 'manual', $o['code'] . ' ' . nums_label($nums));
                if (!empty($_POST['email_them'])) {
                    send_mail($paid ? 'confirmed' : 'reserved', $o['email'], order_mail_data($o));
                }
                if ($paid) upsert_contact($o['email'], $o['first_name'] . ' ' . $o['last_name'], ['club32', 'club32-' . $o['season']]);
                flash("{$o['code']} added: " . nums_label($nums) . '.');
                go('p=orders&s=' . ($paid ? 'paid' : 'pending'));
            case 'presale_import':
                $added = 0; $skipped = [];
                $ins = db()->prepare('INSERT OR IGNORE INTO presale (token, season, number, first_name, last_name, email) VALUES (?, ?, ?, ?, ?, ?)');
                foreach (preg_split('/\R/', (string)$_POST['csv']) as $line) {
                    $c = array_map('trim', str_getcsv($line));
                    if (count($c) < 4 || !ctype_digit($c[0])) { if (trim($line) !== '') $skipped[] = $line; continue; }
                    $n = (int)$c[0];
                    if ($n < 1 || $n > 100 || !filter_var($c[3], FILTER_VALIDATE_EMAIL)) { $skipped[] = $line; continue; }
                    $ins->execute([bin2hex(random_bytes(16)), season(), $n, $c[1], $c[2], strtolower($c[3])]);
                    $ins->rowCount() ? $added++ : $skipped[] = $line;
                }
                audit(me(), 'presale_import', "$added added");
                flash("$added returning members added." . ($skipped ? ' Skipped (bad line or number already listed): ' . implode(' | ', array_slice($skipped, 0, 8)) : ''), $skipped ? 'err' : 'ok');
                go('p=presale');
            case 'presale_send':
                $where = $id ? 'AND rowid = ' . $id : 'AND invited_at IS NULL';
                $rows = db()->query('SELECT rowid, * FROM presale WHERE season = ' . season() . " $where")->fetchAll();
                $until = presale_until();
                $sent = 0;
                foreach ($rows as $r) {
                    $ok = send_mail('presale', $r['email'], [
                        'firstName' => $r['first_name'], 'number' => '#' . $r['number'],
                        'claimUrl' => site_url() . '/club32/?claim=' . $r['token'] . '#numbers',
                        'presaleUntil' => $until ? date('l j F, H:i', $until) : 'TBC',
                        'amount' => money((int)setting('price_pence')),
                    ]);
                    if ($ok) { db()->prepare('UPDATE presale SET invited_at = ? WHERE rowid = ?')->execute([time(), $r['rowid']]); $sent++; }
                }
                audit(me(), 'presale_send', "$sent sent");
                flash("$sent invitation" . ($sent === 1 ? '' : 's') . ' sent.' . ($sent < count($rows) ? ' Some could not be sent; check the BlueFox setup.' : ''), $sent < count($rows) ? 'err' : 'ok');
                go('p=presale');
            case 'presale_delete':
                db()->prepare('DELETE FROM presale WHERE rowid = ?')->execute([$id]);
                flash('Removed from the presale list.');
                go('p=presale');
            case 'draw':
                $label = mb_substr(trim((string)$_POST['label']), 0, 80);
                if ($label === '') { flash('Give the draw a name, for example "Round 1, Oulton Park".', 'err'); go('p=draws'); }
                $q = db()->prepare("SELECT c.number, c.order_id FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = ? AND o.status = 'paid'");
                $q->execute([season()]);
                $pool = [];
                foreach ($q->fetchAll() as $r) $pool[(int)$r['number']] = (int)$r['order_id'];
                $manual = ($_POST['mode'] ?? '') === 'manual';
                if ($manual) {
                    $picks = array_values(array_filter([(int)($_POST['first'] ?? 0), (int)($_POST['second'] ?? 0)]));
                    foreach ($picks as $n) if (!isset($pool[$n])) { flash("#$n is not a paid number this season.", 'err'); go('p=draws'); }
                } else {
                    if (count($pool) < 2) { flash('At least two paid numbers are needed for a draw.', 'err'); go('p=draws'); }
                    $keys = array_keys($pool);
                    $first = $keys[random_int(0, count($keys) - 1)];
                    $rest = array_values(array_diff($keys, [$first]));
                    $picks = [$first, $rest[random_int(0, count($rest) - 1)]];
                }
                if (!$picks) { flash('Enter at least the 1st prize number.', 'err'); go('p=draws'); }
                $db = db();
                $db->beginTransaction();
                $db->prepare('INSERT INTO draws (season, label, method, created_at, created_by) VALUES (?, ?, ?, ?, ?)')->execute([season(), $label, $manual ? 'manual' : 'random', time(), me()]);
                $drawId = (int)$db->lastInsertId();
                foreach ($picks as $i => $n) {
                    $db->prepare('INSERT INTO winners (draw_id, place, number, order_id, prize) VALUES (?, ?, ?, ?, ?)')->execute([$drawId, $i + 1, $n, $pool[$n], setting($i ? 'prize2' : 'prize1')]);
                }
                $db->commit();
                audit(me(), 'draw', "$label: " . nums_label($picks));
                go($manual ? 'p=draws' : "p=reveal&id=$drawId");
            case 'draw_notify':
                $q = db()->prepare('SELECT w.*, d.label, o.first_name, o.last_name, o.email FROM winners w JOIN draws d ON d.id = w.draw_id JOIN orders o ON o.id = w.order_id WHERE w.draw_id = ?');
                $q->execute([$id]);
                $sent = 0;
                foreach ($q->fetchAll() as $w) {
                    if (send_mail('winner', $w['email'], ['firstName' => $w['first_name'], 'number' => '#' . $w['number'], 'prize' => $w['prize'], 'place' => $w['place'] == 1 ? '1st' : '2nd', 'draw' => $w['label']])) {
                        db()->prepare('UPDATE winners SET notified_at = ? WHERE draw_id = ? AND place = ?')->execute([time(), $id, $w['place']]);
                        $sent++;
                    }
                }
                flash("$sent winner email" . ($sent === 1 ? '' : 's') . ' sent.');
                go('p=draws');
            case 'draw_delete':
                db()->prepare('DELETE FROM draws WHERE id = ?')->execute([$id]);
                audit(me(), 'draw_delete', (string)$id);
                flash('Draw deleted.');
                go('p=draws');
            case 'settings':
                foreach (array_keys(SETTING_DEFAULTS) as $k) {
                    if ($k === 'sales_open') { set_setting($k, !empty($_POST[$k]) ? '1' : '0'); continue; }
                    if (!array_key_exists($k, $_POST)) continue;
                    $v = trim((string)$_POST[$k]);
                    if ($k === 'price_pence') $v = (string)max(100, (int)round((float)$v * 100));
                    if (in_array($k, ['season', 'hold_hours', 'reminder_hours', 'max_per_order'], true)) $v = (string)max(1, (int)$v);
                    if ($k === 'presale_until' && $v !== '') $v = str_replace('T', ' ', $v);
                    set_setting($k, mb_substr($v, 0, 200));
                }
                audit(me(), 'settings');
                flash('Settings saved.');
                go('p=settings');
            case 'password':
                $q = db()->prepare('SELECT * FROM users WHERE username = ?');
                $q->execute([me()]);
                $u = $q->fetch();
                $new = (string)$_POST['new'];
                if (!$u || !password_verify((string)$_POST['old'], $u['pass_hash'])) { flash('The current password is wrong.', 'err'); go('p=settings'); }
                if (strlen($new) < 10) { flash('The new password needs at least 10 characters.', 'err'); go('p=settings'); }
                db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
                audit(me(), 'password');
                flash('Password changed.');
                go('p=settings');
        }
    } catch (RuntimeException $e) {
        $m = $e->getMessage();
        flash(str_starts_with($m, 'taken:') ? 'These numbers are no longer free: #' . str_replace(',', ', #', substr($m, 6)) : 'That could not be done (it may have changed meanwhile).', 'err');
    }
    header('Location: ' . ($back !== '' && str_starts_with($back, '/admin/') ? $back : '/admin/'), true, 303);
    exit;
}

// ---------------------------------------------------------------- CSV export

if ($p === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="club32-' . season() . '-members.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Number', 'First name', 'Last name', 'Email', 'Phone', 'Reference', 'Paid on', 'Source']);
    $q = db()->prepare("SELECT c.number, o.* FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = ? AND o.status = 'paid' ORDER BY c.number");
    $q->execute([season()]);
    foreach ($q->fetchAll() as $r) fputcsv($out, [$r['number'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['code'], date('Y-m-d', (int)$r['paid_at']), $r['source']]);
    exit;
}

sweep();

// ---------------------------------------------------------------- pages

$pages = ['dash' => 'Overview', 'orders' => 'Reservations', 'add' => 'Add', 'members' => 'Members', 'presale' => 'Presale', 'draws' => 'Draws', 'stats' => 'Visitors', 'settings' => 'Settings'];
if (!isset($pages[$p]) && $p !== 'reveal') $p = 'dash';
layout_start($pages[$p] ?? 'Draw', true, $p, $pages);

$season = season();
$counts = db()->query("SELECT status, COUNT(*) n FROM orders WHERE season = $season GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$sold = (int)db()->query("SELECT COUNT(*) FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = $season AND o.status = 'paid'")->fetchColumn();
$held = (int)db()->query("SELECT COUNT(*) FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = $season AND o.status = 'pending'")->fetchColumn();
$revenue = (int)db()->query("SELECT COALESCE(SUM(amount_pence), 0) FROM orders WHERE season = $season AND status = 'paid'")->fetchColumn();

function order_card(array $o, string $back): string {
    $nums = nums_label(order_numbers((int)$o['id']));
    $id = (int)$o['id'];
    $status = ['pending' => 'Awaiting payment', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled'][$o['status']];
    $meta = [];
    $meta[] = 'Reserved ' . when((int)$o['created_at']);
    if ($o['status'] === 'pending') $meta[] = '<strong>Hold ends ' . when((int)$o['expires_at']) . '</strong>';
    if ($o['paid_at']) $meta[] = 'Paid ' . when((int)$o['paid_at']) . ($o['handled_by'] ? ' (' . h($o['handled_by']) . ')' : '');
    if ($o['source'] !== 'web') $meta[] = h($o['source']);
    $f = fn(string $a, string $label, string $cls = '', string $extra = '', string $confirm = '') =>
        '<form method="post"' . ($confirm ? ' onsubmit="return confirm(\'' . h($confirm) . '\')"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">'
        . $extra . '<button class="btn ' . $cls . '">' . $label . '</button></form>';
    $actions = '';
    if (in_array($o['status'], ['pending', 'expired'], true)) $actions .= $f('approve', 'Payment received', 'btn--go', '', "Mark {$o['code']} as paid and email {$o['first_name']}?");
    if ($o['status'] === 'pending') $actions .= $f('extend', '+' . setting('hold_hours') . 'h', 'btn--ghost');
    if (in_array($o['status'], ['pending', 'paid'], true)) {
        $actions .= $f('resend', 'Resend email', 'btn--ghost');
        $actions .= $f('cancel', 'Cancel', 'btn--bad', '<label class="inline"><input type="checkbox" name="notify" value="1"' . ($o['status'] === 'pending' ? ' checked' : '') . '> email them</label>', "Cancel {$o['code']} and release {$nums}?");
    }
    return '<article class="order order--' . $o['status'] . '">
        <div class="order__top"><span class="order__code">' . h($o['code']) . '</span><span class="tag tag--' . $o['status'] . '">' . $status . '</span></div>
        <div class="order__nums">' . h($nums) . ' <span class="muted">· ' . money((int)$o['amount_pence']) . '</span></div>
        <div class="order__who"><strong>' . h($o['first_name'] . ' ' . $o['last_name']) . '</strong> · <a href="mailto:' . h($o['email']) . '">' . h($o['email']) . '</a>' . ($o['phone'] ? ' · <a href="tel:' . h($o['phone']) . '">' . h($o['phone']) . '</a>' : '') . '</div>
        <div class="order__meta">' . implode(' · ', $meta) . '</div>
        <form method="post" class="order__note">' . csrf_field() . '<input type="hidden" name="a" value="note"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '"><input name="note" value="' . h($o['note']) . '" placeholder="Note (only you see this)"><button class="btn btn--ghost">Save</button></form>
        <div class="order__actions">' . $actions . '</div>
    </article>';
}

switch ($p) {
case 'dash':
    $since = date('Y-m-d', strtotime('-6 days'));
    $visitors = (int)db()->query("SELECT COUNT(*) FROM uv WHERE day >= '$since'")->fetchColumn();
    $views = (int)db()->query("SELECT COALESCE(SUM(n), 0) FROM pv WHERE day >= '$since'")->fetchColumn();
    $open = setting('sales_open') === '1';
    echo '<div class="tiles">
      <div class="tile"><span>Numbers sold</span><strong>' . $sold . '<small>/100</small></strong></div>
      <div class="tile"><span>Paid in</span><strong>' . money($revenue) . '</strong></div>
      <div class="tile"><span>Awaiting payment</span><strong>' . (int)($counts['pending'] ?? 0) . '<small> res. · ' . $held . ' nos.</small></strong></div>
      <div class="tile"><span>Visitors, 7 days</span><strong>' . $visitors . '<small> · ' . $views . ' views</small></strong></div>
    </div>';
    echo '<p class="box">Club32 ' . $season . ' is <strong>' . ($open ? 'open to everyone' : 'closed to new members') . '</strong>'
        . (presale_active() ? ' · presale for returning members until <strong>' . when(presale_until()) . '</strong>' : '')
        . '. <a href="' . url(['p' => 'settings']) . '">Change</a></p>';
    if (setting('bank_account') === '') echo '<p class="flash flash--err">Bank details are not set yet, so the payment emails have no account number. <a href="' . url(['p' => 'settings']) . '">Add them in Settings.</a></p>';
    echo '<h2>Awaiting payment</h2>';
    $rows = db()->query("SELECT * FROM orders WHERE season = $season AND status = 'pending' ORDER BY expires_at")->fetchAll();
    echo $rows ? implode('', array_map(fn($o) => order_card($o, '/admin/'), $rows)) : '<p class="muted">Nothing waiting. 🏁</p>';
    break;

case 'orders':
    $s = (string)($_GET['s'] ?? 'pending');
    $qstr = trim((string)($_GET['q'] ?? ''));
    echo '<nav class="tabs">';
    foreach (['pending' => 'Awaiting payment', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $label) {
        echo '<a href="' . url(['p' => 'orders', 's' => $k]) . '"' . ($k === $s ? ' class="on"' : '') . '>' . $label . ($k !== 'all' ? ' <small>' . (int)($counts[$k] ?? 0) . '</small>' : '') . '</a>';
    }
    echo '</nav><form class="search"><input type="hidden" name="p" value="orders"><input type="hidden" name="s" value="' . h($s) . '"><input name="q" value="' . h($qstr) . '" placeholder="Search reference, name, email or number"><button class="btn btn--ghost">Search</button></form>';
    $sql = 'SELECT DISTINCT o.* FROM orders o LEFT JOIN order_numbers n ON n.order_id = o.id WHERE o.season = ?';
    $args = [$season];
    if ($s !== 'all') { $sql .= ' AND o.status = ?'; $args[] = $s; }
    if ($qstr !== '') {
        $sql .= ' AND (o.code LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR o.email LIKE ? OR n.number = ?)';
        array_push($args, "%$qstr%", "%$qstr%", "%$qstr%", "%$qstr%", ctype_digit($qstr) ? (int)$qstr : -1);
    }
    $sql .= $s === 'pending' ? ' ORDER BY o.expires_at' : ' ORDER BY o.created_at DESC';
    $q = db()->prepare($sql . ' LIMIT 200');
    $q->execute($args);
    $rows = $q->fetchAll();
    $back = '/admin/?' . http_build_query(['p' => 'orders', 's' => $s, 'q' => $qstr]);
    echo $rows ? implode('', array_map(fn($o) => order_card($o, $back), $rows)) : '<p class="muted">No reservations here.</p>';
    break;

case 'add':
    echo '<form method="post" class="box form">' . csrf_field() . '<input type="hidden" name="a" value="manual">
      <p class="muted">For numbers sold in person, by cash or by a transfer that came in without a reservation.</p>
      <label>Numbers<input name="numbers" required placeholder="e.g. 13, 27" inputmode="numeric"></label>
      <div class="row"><label>First name<input name="first_name" required></label><label>Last name<input name="last_name"></label></div>
      <div class="row"><label>Email<input name="email" type="email" required></label><label>Phone<input name="phone" type="tel"></label></div>
      <label>Status<select name="status"><option value="paid">Paid already</option><option value="pending">Reserve, awaiting payment</option></select></label>
      <label>Note<input name="note" placeholder="e.g. cash at Brands Hatch"></label>
      <label class="inline"><input type="checkbox" name="email_them" value="1" checked> Email them (confirmation, or the payment details)</label>
      <button class="btn btn--go">Add</button></form>';
    break;

case 'members':
    $q = db()->prepare("SELECT c.number, o.* FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = ? ORDER BY c.number");
    $q->execute([$season]);
    $by = [];
    foreach ($q->fetchAll() as $r) $by[(int)$r['number']] = $r;
    echo '<p><a class="btn btn--ghost" href="' . url(['p' => 'export']) . '">Download paid members (CSV)</a></p>';
    echo '<div class="board">';
    for ($n = 1; $n <= 100; $n++) {
        $r = $by[$n] ?? null;
        $cls = $r ? ($r['status'] === 'paid' ? 'paid' : 'pending') : 'free';
        $title = $r ? h($r['first_name'] . ' ' . $r['last_name'] . ' · ' . $r['code']) : 'free';
        $link = $r ? url(['p' => 'orders', 's' => 'all', 'q' => $r['code']]) : url(['p' => 'add']);
        echo '<a class="cell cell--' . $cls . '" href="' . $link . '" title="' . $title . '"><b>' . $n . '</b><span>' . ($r ? h($r['first_name']) : '') . '</span></a>';
    }
    echo '</div><table class="tbl"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Ref</th><th>Status</th></tr></thead><tbody>';
    foreach ($by as $n => $r) {
        echo '<tr><td><b>' . $n . '</b></td><td>' . h($r['first_name'] . ' ' . $r['last_name']) . '</td><td>' . h($r['email']) . '</td><td>' . h($r['phone']) . '</td><td>' . h($r['code']) . '</td><td><span class="tag tag--' . $r['status'] . '">' . ($r['status'] === 'paid' ? 'Paid' : 'Awaiting') . '</span></td></tr>';
    }
    echo '</tbody></table>';
    break;

case 'presale':
    $until = presale_until();
    echo '<p class="box">Last season\'s members get a personal link that holds their old number for them'
        . ($until ? ' until <strong>' . when($until) . '</strong>' . (time() > $until ? ' (<strong>ended</strong>)' : '') : ' (set the end date in <a href="' . url(['p' => 'settings']) . '">Settings</a>)')
        . '. While the presale runs, those numbers show as "Held for a member" on the site. Club32 sales to everyone else open with the "Open to everyone" switch in Settings.</p>';
    echo '<form method="post" class="box form">' . csrf_field() . '<input type="hidden" name="a" value="presale_import">
      <label>Add returning members, one per line: <code>number, first name, last name, email</code><textarea name="csv" rows="6" placeholder="13, George, Smith, george@example.com"></textarea></label>
      <button class="btn">Add to list</button></form>';
    $rows = db()->query("SELECT p.rowid, p.*, (SELECT o.status FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = p.season AND c.number = p.number) st FROM presale p WHERE p.season = $season ORDER BY p.number")->fetchAll();
    $notInvited = count(array_filter($rows, fn($r) => !$r['invited_at']));
    if ($rows) {
        echo '<form method="post" onsubmit="return confirm(\'Email ' . $notInvited . ' returning members their personal link?\')">' . csrf_field() . '<input type="hidden" name="a" value="presale_send"><button class="btn btn--go"' . ($notInvited ? '' : ' disabled') . '>Email the ' . $notInvited . ' not yet invited</button></form>';
        echo '<table class="tbl"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Invited</th><th>Number now</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $state = $r['st'] === 'paid' ? '<span class="tag tag--paid">Paid</span>' : ($r['st'] === 'pending' ? '<span class="tag tag--pending">Reserved</span>' : '<span class="muted">not yet</span>');
            $link = site_url() . '/club32/?claim=' . $r['token'] . '#numbers';
            echo '<tr><td><b>' . (int)$r['number'] . '</b></td><td>' . h($r['first_name'] . ' ' . $r['last_name']) . '</td><td>' . h($r['email']) . '</td><td>' . ($r['invited_at'] ? when((int)$r['invited_at']) : '-') . '</td><td>' . $state . '</td>
                <td class="nowrap"><button type="button" class="btn btn--ghost" onclick="navigator.clipboard.writeText(\'' . h($link) . '\');this.textContent=\'Copied\'">Copy link</button>
                <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="a" value="presale_send"><input type="hidden" name="id" value="' . (int)$r['rowid'] . '"><button class="btn btn--ghost">' . ($r['invited_at'] ? 'Resend' : 'Send') . '</button></form>
                <form method="post" class="inline" onsubmit="return confirm(\'Remove #' . (int)$r['number'] . ' from the presale list?\')">' . csrf_field() . '<input type="hidden" name="a" value="presale_delete"><input type="hidden" name="id" value="' . (int)$r['rowid'] . '"><button class="btn btn--bad">×</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    break;

case 'draws':
    echo '<form method="post" class="box form">' . csrf_field() . '<input type="hidden" name="a" value="draw">
      <p class="muted">' . $sold . ' paid numbers are in the draw. A random draw picks the 1st and 2nd prize from them, then shows a reveal screen you can film for the live video.</p>
      <label>Draw name<input name="label" required placeholder="e.g. Round 1, Oulton Park"></label>
      <label class="inline"><input type="radio" name="mode" value="random" checked onchange="m.hidden=true"> Draw at random here</label>
      <label class="inline"><input type="radio" name="mode" value="manual" onchange="m.hidden=false"> We drew it ourselves: just record the numbers</label>
      <div class="row" id="m" hidden><label>1st prize number<input name="first" inputmode="numeric"></label><label>2nd prize number<input name="second" inputmode="numeric"></label></div>
      <p class="muted">1st: ' . h(setting('prize1')) . ' · 2nd: ' . h(setting('prize2')) . '</p>
      <button class="btn btn--go">Draw</button></form>';
    $draws = db()->query("SELECT * FROM draws WHERE season = $season ORDER BY created_at DESC")->fetchAll();
    foreach ($draws as $d) {
        $q = db()->prepare('SELECT w.*, o.first_name, o.last_name, o.email FROM winners w LEFT JOIN orders o ON o.id = w.order_id WHERE w.draw_id = ? ORDER BY place');
        $q->execute([$d['id']]);
        $ws = $q->fetchAll();
        $notified = count(array_filter($ws, fn($w) => $w['notified_at']));
        echo '<article class="order"><div class="order__top"><span class="order__code">' . h($d['label']) . '</span><span class="muted">' . when((int)$d['created_at']) . ' · ' . h($d['method']) . '</span></div>';
        foreach ($ws as $w) echo '<div class="order__who"><b class="big">#' . (int)$w['number'] . '</b> ' . ($w['place'] == 1 ? '1st' : '2nd') . ': <strong>' . h($w['first_name'] . ' ' . $w['last_name']) . '</strong> · ' . h($w['email']) . ($w['notified_at'] ? ' <span class="tag tag--paid">emailed</span>' : '') . '</div>';
        echo '<div class="order__actions">'
            . '<a class="btn btn--ghost" href="' . url(['p' => 'reveal', 'id' => $d['id']]) . '">Reveal screen</a>'
            . '<form method="post" onsubmit="return confirm(\'Email the winners?\')">' . csrf_field() . '<input type="hidden" name="a" value="draw_notify"><input type="hidden" name="id" value="' . (int)$d['id'] . '"><button class="btn">' . ($notified ? 'Email winners again' : 'Email winners') . '</button></form>'
            . '<form method="post" onsubmit="return confirm(\'Delete this draw? It disappears from the site too.\')">' . csrf_field() . '<input type="hidden" name="a" value="draw_delete"><input type="hidden" name="id" value="' . (int)$d['id'] . '"><button class="btn btn--bad">Delete</button></form></div></article>';
    }
    break;

case 'reveal':
    $q = db()->prepare('SELECT * FROM draws WHERE id = ?');
    $q->execute([(int)($_GET['id'] ?? 0)]);
    $d = $q->fetch();
    if (!$d) { echo '<p>Draw not found.</p>'; break; }
    $q = db()->prepare('SELECT w.*, o.first_name FROM winners w LEFT JOIN orders o ON o.id = w.order_id WHERE w.draw_id = ? ORDER BY place DESC');
    $q->execute([$d['id']]);
    $ws = $q->fetchAll();
    $pool = db()->query("SELECT c.number FROM claims c JOIN orders o ON o.id = c.order_id WHERE c.season = $season AND o.status = 'paid'")->fetchAll(PDO::FETCH_COLUMN);
    echo '<div class="reveal"><p class="reveal__label">Club32 · ' . h($d['label']) . '</p>';
    foreach ($ws as $w) {
        echo '<div class="reveal__slot" data-final="' . (int)$w['number'] . '" data-name="' . h(public_first_name((string)$w['first_name'])) . '">
          <span class="reveal__place">' . ($w['place'] == 1 ? '1st prize' : '2nd prize') . '</span>
          <span class="reveal__num">?</span><span class="reveal__name"></span><span class="reveal__prize">' . h($w['prize']) . '</span>
          <button type="button" class="btn btn--go">Reveal</button></div>';
    }
    echo '</div><script>
      const pool = ' . json_encode(array_map('intval', $pool)) . ';
      document.querySelectorAll(".reveal__slot").forEach((s) => {
        s.querySelector("button").addEventListener("click", (e) => {
          e.target.hidden = true;
          const num = s.querySelector(".reveal__num"), start = performance.now(), dur = 4000;
          (function tick(t) {
            const k = (t - start) / dur;
            if (k < 1) { num.textContent = pool[Math.floor(Math.random() * pool.length)] || "?"; setTimeout(() => requestAnimationFrame(tick), 40 + 260 * k * k); return; }
            num.textContent = s.dataset.final; s.classList.add("is-done");
            s.querySelector(".reveal__name").textContent = s.dataset.name;
          })(start);
        });
      });
    </script>';
    break;

case 'stats':
    $days = 30;
    $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $uv = db()->query("SELECT day, COUNT(*) n FROM uv WHERE day >= '$since' GROUP BY day")->fetchAll(PDO::FETCH_KEY_PAIR);
    $pvd = db()->query("SELECT day, SUM(n) n FROM pv WHERE day >= '$since' GROUP BY day")->fetchAll(PDO::FETCH_KEY_PAIR);
    $max = max([1, ...array_values($pvd)]);
    $tv = array_sum($uv); $tp = array_sum($pvd);
    echo '<div class="tiles"><div class="tile"><span>Visitors, 30 days</span><strong>' . $tv . '</strong></div><div class="tile"><span>Page views, 30 days</span><strong>' . $tp . '</strong></div>'
        . '<div class="tile"><span>Today</span><strong>' . (int)($uv[date('Y-m-d')] ?? 0) . '<small> visitors</small></strong></div></div>';
    echo '<div class="chart" role="img" aria-label="Daily visitors and page views">';
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $v = (int)($uv[$d] ?? 0); $pv = (int)($pvd[$d] ?? 0);
        echo '<div class="bar" title="' . date('D j M', strtotime($d)) . ': ' . $v . ' visitors, ' . $pv . ' views"><i style="height:' . round($pv / $max * 100) . '%"></i><b style="height:' . round($v / $max * 100) . '%"></b></div>';
    }
    echo '</div><p class="muted">Light bars: page views · bright bars: visitors. Counted without cookies; visitors who block tracking are not counted.</p><div class="row">';
    $top = db()->query("SELECT path, SUM(n) n FROM pv WHERE day >= '$since' GROUP BY path ORDER BY n DESC LIMIT 15")->fetchAll();
    echo '<table class="tbl"><thead><tr><th>Page</th><th>Views</th></tr></thead><tbody>';
    foreach ($top as $r) echo '<tr><td>' . h($r['path']) . '</td><td>' . (int)$r['n'] . '</td></tr>';
    echo '</tbody></table>';
    $refs = db()->query("SELECT host, SUM(n) n FROM refs WHERE day >= '$since' GROUP BY host ORDER BY n DESC LIMIT 15")->fetchAll();
    echo '<table class="tbl"><thead><tr><th>Came from</th><th>Visits</th></tr></thead><tbody>';
    foreach ($refs as $r) echo '<tr><td>' . h($r['host']) . '</td><td>' . (int)$r['n'] . '</td></tr>';
    if (!$refs) echo '<tr><td colspan="2" class="muted">No referring sites yet.</td></tr>';
    echo '</tbody></table></div>';
    break;

case 'settings':
    $v = fn(string $k) => h(setting($k));
    $until = setting('presale_until');
    echo '<form method="post" class="box form">' . csrf_field() . '<input type="hidden" name="a" value="settings">
      <h2>Sales</h2>
      <label class="inline switch"><input type="checkbox" name="sales_open" value="1"' . (setting('sales_open') === '1' ? ' checked' : '') . '> Open to everyone (when off, only returning members with their link can reserve)</label>
      <label>Presale ends (returning members keep their old number until then)<input type="datetime-local" name="presale_until" value="' . h($until !== '' ? str_replace(' ', 'T', $until) : '') . '"></label>
      <div class="row"><label>Season<input name="season" type="number" value="' . $v('season') . '"></label><label>Price per number (£)<input name="price_pence" type="number" step="0.01" value="' . number_format((int)setting('price_pence') / 100, 2, '.', '') . '"></label></div>
      <div class="row"><label>Hold time (hours)<input name="hold_hours" type="number" value="' . $v('hold_hours') . '"></label><label>Reminder (hours before the hold ends)<input name="reminder_hours" type="number" value="' . $v('reminder_hours') . '"></label><label>Max numbers per reservation<input name="max_per_order" type="number" value="' . $v('max_per_order') . '"></label></div>
      <h2>Bank details (shown to buyers)</h2>
      <label>Account name<input name="bank_name" value="' . $v('bank_name') . '"></label>
      <div class="row"><label>Sort code<input name="bank_sort" value="' . $v('bank_sort') . '" placeholder="00-00-00"></label><label>Account number<input name="bank_account" value="' . $v('bank_account') . '" inputmode="numeric"></label></div>
      <h2>Prizes</h2>
      <label>1st prize<input name="prize1" value="' . $v('prize1') . '"></label>
      <label>2nd prize<input name="prize2" value="' . $v('prize2') . '"></label>
      <button class="btn btn--go">Save settings</button></form>';
    echo '<form method="post" class="box form">' . csrf_field() . '<input type="hidden" name="a" value="password"><h2>Change your password</h2>
      <div class="row"><label>Current password<input type="password" name="old" autocomplete="current-password" required></label><label>New password (10+ characters)<input type="password" name="new" autocomplete="new-password" minlength="10" required></label></div>
      <button class="btn">Change password</button></form>';
    echo '<h2>Recent activity</h2><table class="tbl"><tbody>';
    foreach (db()->query('SELECT * FROM audit ORDER BY id DESC LIMIT 30')->fetchAll() as $r) {
        echo '<tr><td class="nowrap">' . when((int)$r['at']) . '</td><td>' . h($r['user'] ?? 'system') . '</td><td>' . h($r['action']) . '</td><td>' . h($r['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';
    break;
}
layout_end();

// ---------------------------------------------------------------- layout

function layout_start(string $title, bool $nav, string $p = '', array $pages = []): void {
    $pending = 0;
    if ($nav) $pending = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND season = " . season())->fetchColumn();
    echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex, nofollow"><title>' . h($title) . ' · #32 admin</title>
      <link rel="icon" href="/favicon.svg" type="image/svg+xml"><style>' . admin_css() . '</style></head><body>';
    if ($nav) {
        echo '<header class="top"><a class="brand" href="/admin/">#32 <span>admin</span></a><nav>';
        foreach ($pages as $k => $label) {
            echo '<a href="' . url(['p' => $k]) . '"' . ($k === $p ? ' class="on"' : '') . '>' . $label . ($k === 'orders' && $pending ? ' <em>' . $pending . '</em>' : '') . '</a>';
        }
        echo '</nav><form method="post" action="/admin/?p=logout">' . csrf_field() . '<button class="btn btn--ghost">Sign out ' . h(me()) . '</button></form></header>';
    }
    echo '<main>';
    if ($nav) echo '<h1>' . h($title) . '</h1>';
    foreach ($_SESSION['flash'] ?? [] as [$k, $m]) echo '<p class="flash flash--' . $k . '">' . h($m) . '</p>';
    unset($_SESSION['flash']);
}

function layout_end(): void { echo '</main></body></html>'; }

function admin_css(): string {
    return <<<'CSS'
:root { --a: #d6f20a; --p: #6d4bc9; --bg: #0b0b0d; --s: #15151b; --s2: #1d1d26; --t: #fff; --t2: #9ca3af; --line: rgb(255 255 255 / .1); --bad: #ff6b6b; color-scheme: dark; }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--t); font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
a { color: var(--a); }
main { max-width: 1100px; margin: 0 auto; padding: 1rem 1rem 4rem; }
h1 { font-size: 1.6rem; margin: .5rem 0 1rem; } h2 { font-size: 1.15rem; margin: 2rem 0 .75rem; }
.top { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .6rem 1rem; background: rgb(11 11 13 / .95); border-bottom: 1px solid var(--line); }
.brand { font-weight: 900; font-style: italic; font-size: 1.3rem; text-decoration: none; } .brand span { color: var(--t2); font-weight: 400; font-style: normal; font-size: .9rem; }
.top nav { display: flex; flex-wrap: wrap; gap: .25rem; flex: 1; }
.top nav a { color: var(--t2); text-decoration: none; padding: .35rem .6rem; border-radius: 4px; }
.top nav a.on { background: var(--a); color: #000; } .top nav em { font-style: normal; background: var(--p); color: #fff; border-radius: 9px; padding: 0 .4rem; font-size: 12px; }
.btn { display: inline-flex; align-items: center; gap: .3rem; padding: .5rem .9rem; border: 1px solid var(--a); background: var(--a); color: #000; font: inherit; font-weight: 600; border-radius: 4px; cursor: pointer; text-decoration: none; }
.btn--go { background: var(--a); } .btn--ghost { background: transparent; color: var(--t); border-color: var(--line); } .btn--bad { background: transparent; color: var(--bad); border-color: rgb(255 107 107 / .4); }
.btn:disabled { opacity: .4; cursor: not-allowed; }
.box { background: var(--s); border: 1px solid var(--line); border-radius: 6px; padding: 1rem 1.25rem; margin: 0 0 1rem; }
.form { display: grid; gap: .9rem; } .form h2 { margin: .5rem 0 0; }
label { display: grid; gap: .3rem; color: var(--t2); font-size: 13px; }
label.inline { display: inline-flex; align-items: center; gap: .4rem; font-size: 14px; color: var(--t); }
input, select, textarea { width: 100%; padding: .6rem .7rem; background: var(--bg); color: var(--t); border: 1px solid var(--line); border-radius: 4px; font: inherit; font-size: 16px; }
label.inline input { width: auto; } input:focus, select:focus, textarea:focus { outline: 2px solid var(--a); outline-offset: 0; }
.row { display: grid; gap: 1rem; } @media (min-width: 700px) { .row { grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); } }
.login { max-width: 380px; margin: 12vh auto 0; display: grid; gap: 1rem; } .login h1 { font-size: 1.3rem; margin: 0; }
.flash { padding: .7rem 1rem; border-radius: 4px; border-left: 4px solid var(--a); background: rgb(214 242 10 / .08); margin: 0 0 1rem; }
.flash--err { border-color: var(--bad); background: rgb(255 107 107 / .08); }
.muted { color: var(--t2); } .nowrap { white-space: nowrap; } form.inline { display: inline; }
.tiles { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 1rem; }
.tile { background: var(--s); border: 1px solid var(--line); border-radius: 6px; padding: .9rem 1rem; display: grid; gap: .2rem; }
.tile span { color: var(--t2); font-size: 13px; } .tile strong { font-size: 1.7rem; line-height: 1.1; } .tile small { font-size: .85rem; color: var(--t2); font-weight: 400; }
.tabs { display: flex; flex-wrap: wrap; gap: .25rem; margin-bottom: .75rem; }
.tabs a { padding: .4rem .8rem; border: 1px solid var(--line); border-radius: 4px; color: var(--t2); text-decoration: none; } .tabs a.on { border-color: var(--a); color: var(--a); }
.search { display: flex; gap: .5rem; margin-bottom: 1rem; }
.order { background: var(--s); border: 1px solid var(--line); border-left: 4px solid var(--line); border-radius: 6px; padding: .9rem 1rem; margin-bottom: .75rem; display: grid; gap: .45rem; }
.order--pending { border-left-color: var(--p); } .order--paid { border-left-color: var(--a); } .order--expired, .order--cancelled { opacity: .75; }
.order__top { display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap; } .order__code { font-family: ui-monospace, monospace; font-weight: 700; font-size: 1.05rem; }
.order__nums { font-size: 1.35rem; font-weight: 800; color: var(--a); } .order__meta { font-size: 13px; color: var(--t2); }
.order__note { display: flex; gap: .5rem; } .order__note input { padding: .4rem .6rem; font-size: 14px; }
.order__actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; } .order__actions form { display: inline-flex; gap: .5rem; align-items: center; }
.tag { font-size: 12px; padding: .1rem .5rem; border-radius: 9px; background: var(--s2); white-space: nowrap; }
.tag--pending { background: var(--p); } .tag--paid { background: var(--a); color: #000; } .tag--expired, .tag--cancelled { color: var(--t2); }
.big { font-size: 1.4rem; color: var(--a); }
.tbl { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 14px; display: block; overflow-x: auto; }
.tbl th, .tbl td { text-align: left; padding: .45rem .6rem; border-bottom: 1px solid var(--line); vertical-align: middle; } .tbl th { color: var(--t2); font-weight: 500; }
.board { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; } @media (min-width: 600px) { .board { grid-template-columns: repeat(10, 1fr); } }
.board .cell { aspect-ratio: 1; display: grid; place-items: center; align-content: center; text-decoration: none; color: var(--t2); background: var(--s); border: 1px solid var(--line); border-radius: 3px; font-size: 11px; overflow: hidden; }
.board .cell b { font-size: 1.1rem; color: var(--t); } .board .cell span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 0 2px; }
.board .cell--paid { background: rgb(214 242 10 / .15); border-color: var(--a); } .board .cell--pending { background: rgb(109 75 201 / .3); border-color: var(--p); }
.chart { display: flex; align-items: flex-end; gap: 3px; height: 160px; background: var(--s); border: 1px solid var(--line); border-radius: 6px; padding: .75rem; }
.bar { flex: 1; height: 100%; position: relative; } .bar i, .bar b { position: absolute; bottom: 0; left: 0; right: 0; border-radius: 2px 2px 0 0; }
.bar i { background: rgb(214 242 10 / .25); } .bar b { background: var(--a); left: 25%; right: 25%; }
.reveal { min-height: 75vh; display: grid; gap: 2rem; place-content: center; text-align: center; }
.reveal__label { font-size: 1.2rem; color: var(--t2); text-transform: uppercase; letter-spacing: .15em; margin: 0; }
.reveal__slot { display: grid; gap: .5rem; justify-items: center; padding: 2rem 3rem; background: var(--s); border: 2px solid var(--line); border-radius: 12px; }
.reveal__slot.is-done { border-color: var(--a); box-shadow: 0 0 60px -10px rgb(214 242 10 / .6); }
.reveal__place { text-transform: uppercase; letter-spacing: .2em; color: var(--t2); }
.reveal__num { font-size: clamp(5rem, 20vw, 11rem); font-weight: 900; font-style: italic; line-height: 1; color: var(--a); font-variant-numeric: tabular-nums; }
.reveal__name { font-size: 2rem; font-weight: 700; min-height: 2.5rem; } .reveal__prize { color: var(--t2); }
code { background: var(--s2); padding: 0 .3rem; border-radius: 3px; }
CSS;
}

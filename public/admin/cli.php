<?php
// Command line only. On the server:
//   sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php add-user lewis      (asks for a password)
//   sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php set-password lewis
//   sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php list-users
//   sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php sweep               (cron: reminders + expiry)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/_lib/club32.php';

function ask_password(): string {
    if (getenv('LSR_PASSWORD')) return (string)getenv('LSR_PASSWORD');
    fwrite(STDERR, 'Password (10+ characters): ');
    if (stream_isatty(STDIN)) shell_exec('stty -echo');
    $p = trim((string)fgets(STDIN));
    if (stream_isatty(STDIN)) shell_exec('stty echo');
    fwrite(STDERR, "\n");
    if (strlen($p) < 10) { fwrite(STDERR, "Too short.\n"); exit(1); }
    return $p;
}

[$cmd, $arg] = [$argv[1] ?? '', strtolower($argv[2] ?? '')];
switch ($cmd) {
    case 'add-user':
        if (!preg_match('/^[a-z0-9._-]{2,32}$/', $arg)) { fwrite(STDERR, "Usage: add-user <name>\n"); exit(1); }
        db()->prepare('INSERT INTO users (username, pass_hash, created_at) VALUES (?, ?, ?)')->execute([$arg, password_hash(ask_password(), PASSWORD_DEFAULT), time()]);
        audit('cli', 'add-user', $arg);
        echo "User $arg created.\n";
        break;
    case 'set-password':
        $q = db()->prepare('UPDATE users SET pass_hash = ? WHERE username = ?');
        $q->execute([password_hash(ask_password(), PASSWORD_DEFAULT), $arg]);
        echo $q->rowCount() ? "Password changed.\n" : "No such user.\n";
        break;
    case 'list-users':
        foreach (db()->query('SELECT username, last_login FROM users') as $u) echo $u['username'], '  last login: ', $u['last_login'] ? date('Y-m-d H:i', (int)$u['last_login']) : 'never', "\n";
        break;
    case 'sweep':
        sweep(true);
        break;
    default:
        fwrite(STDERR, "Commands: add-user <name> | set-password <name> | list-users | sweep\n");
        exit(1);
}

<?php
// Command line only. On the server (sudo -u www-data php /var/www/lewissmartracing.com/admin/cli.php ...):
//   add-user <email> [admin|developer] [name]   asks for a password (or reads LSR_PASSWORD)
//   invite <email> [admin|developer] [name]     no password: emails a set-password link instead
//   set-role <email|username> <admin|developer>
//   set-email <username> <email>                for accounts made before sign-in by email
//   set-password <email|username>
//   list-users
//   sweep                                       cron: payment reminders and expiry
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
function fail(string $msg): never { fwrite(STDERR, $msg . "\n"); exit(1); }
function find(string $login): array { return user_by_login($login) ?? fail("No such user: $login"); }

$cmd = $argv[1] ?? '';
$arg = strtolower(trim($argv[2] ?? ''));
$arg2 = strtolower(trim($argv[3] ?? ''));
switch ($cmd) {
    case 'add-user':
    case 'invite':
        $role = $arg2 ?: 'admin';
        try { $id = create_user($arg, implode(' ', array_slice($argv, 4)), $role, $cmd === 'add-user' ? ask_password() : null); }
        catch (InvalidArgumentException $e) { fail($e->getMessage() . "\nUsage: $cmd <email> [admin|developer] [name]"); }
        audit('cli', $cmd, "$arg ($role)");
        if ($cmd === 'invite') echo send_password_link(user_by_id($id), 'admin_invite') ? "Invite emailed to $arg.\n" : "User created, but the email could not be sent.\n";
        else echo "User $arg created ($role). Sign in at " . site_url() . "/admin/ with the email.\n";
        break;
    case 'set-role':
        $u = find($arg);
        if (!isset(ROLES[$arg2])) fail('Role must be admin or developer.');
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$arg2, $u['id']]);
        audit('cli', 'set-role', user_label($u) . " → $arg2");
        echo user_label($u) . " is now $arg2.\n";
        break;
    case 'set-email':
        $u = find($arg);
        if (!filter_var($arg2, FILTER_VALIDATE_EMAIL)) fail('Usage: set-email <username> <email>');
        db()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$arg2, $u['id']]);
        echo user_label($u) . " now signs in with $arg2.\n";
        break;
    case 'set-password':
        $u = find($arg);
        set_user_password((int)$u['id'], ask_password());
        echo "Password changed.\n";
        break;
    case 'list-users':
        foreach (db()->query('SELECT * FROM users ORDER BY id') as $u) {
            printf("%-32s %-10s %-16s last login: %s\n", $u['email'] ?: "($u[username], no email)", $u['role'], $u['name'], $u['last_login'] ? date('Y-m-d H:i', (int)$u['last_login']) : 'never');
        }
        break;
    case 'sweep':
        sweep(true);
        break;
    default:
        fail("Commands: add-user <email> [admin|developer] [name] | invite <email> [role] [name] | set-role <user> <role> | set-email <username> <email> | set-password <user> | list-users | sweep");
}

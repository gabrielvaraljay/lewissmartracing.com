#!/usr/bin/env bash
# One-off server setup for Club32 and the admin panel. Run from your machine after the first deploy:
#   bash scripts/setup-club32.sh
# It does, on the server (via sudo):
#   1. checks PHP has SQLite (installs php-sqlite3 if missing, after asking)
#   2. creates /var/lib/lewissmartracing (the database lives here, outside the web root)
#   3. writes /etc/lewissmartracing/club32.env with the BlueFox Club32 email ids
#   4. adds a cron job every 10 minutes for reminders and releasing expired holds
#   5. creates the first developer (asks for the email and a password)
#   6. checks that /admin/ is reachable (nginx needs index.php as an index file)
set -euo pipefail
SSH="ssh -t -o ControlPath=$HOME/.ssh/cm-hetzner-lsr gabriel@78.47.73.104"
WEB=/var/www/lewissmartracing.com

read -rp "First developer email: " ADMIN

$SSH "set -e
php -m | grep -qi pdo_sqlite || { read -rp 'PHP has no SQLite. Install php-sqlite3 and restart php-fpm? [y/N] ' A; [ \"\$A\" = y ] && sudo apt-get install -y php-sqlite3 && sudo systemctl restart \$(systemctl list-units --type=service --no-legend 'php*-fpm*' | awk '{print \$1}' | head -1); }

sudo install -d -m 770 -o www-data -g www-data /var/lib/lewissmartracing

sudo tee /etc/lewissmartracing/club32.env >/dev/null <<'ENV'
# Club32 BlueFox transactional email ids (not secret). The API key stays in bluefox.env.
BLUEFOX_C32_RESERVED_ID=6af23d4422a3fca8ec0f5992
BLUEFOX_C32_REMINDER_ID=6af23d4422a3fca8ec0f599a
BLUEFOX_C32_EXPIRED_ID=6af23d4422a3fca8ec0f59a2
BLUEFOX_C32_CONFIRMED_ID=6af23d4422a3fca8ec0f59aa
BLUEFOX_C32_CANCELLED_ID=6af23d4422a3fca8ec0f59b2
BLUEFOX_C32_ADMIN_ID=6af23d4422a3fca8ec0f59ba
BLUEFOX_C32_PRESALE_ID=6af23d4422a3fca8ec0f59c2
BLUEFOX_C32_WINNER_ID=6af23d4422a3fca8ec0f59ca
ENV
sudo chown root:www-data /etc/lewissmartracing/club32.env && sudo chmod 640 /etc/lewissmartracing/club32.env

echo '*/10 * * * * www-data php $WEB/admin/cli.php sweep >/dev/null 2>&1' | sudo tee /etc/cron.d/lewissmartracing-club32 >/dev/null
sudo chmod 644 /etc/cron.d/lewissmartracing-club32

sudo -u www-data php $WEB/admin/cli.php add-user '$ADMIN' developer
"

code=$(curl -s -o /dev/null -w '%{http_code}' https://lewissmartracing.com/admin/)
echo "GET /admin/ -> HTTP $code"
if [ "$code" != 200 ]; then
  cat <<'MSG'
/admin/ did not answer 200. In the nginx server block for lewissmartracing.com, make sure index.php is an index file:
    index index.html index.php;
(then: sudo nginx -t && sudo systemctl reload nginx). /admin/index.php works in the meantime.
If the temporary password gate redirects /admin/, that is expected until the gate is removed.
MSG
fi

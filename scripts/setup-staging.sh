#!/usr/bin/env bash
# One-off setup of staging.lewissmartracing.com. Run from the devbox (needs gh and SSH to the server):
#
#   LSR_SSH_KEY=~/.ssh/hetzner.tf bash scripts/setup-staging.sh
#
# Before running: DNS A record  staging.lewissmartracing.com -> 78.47.73.104  (GoDaddy).
#
# What it does:
#   1. checks the DNS record points at the server
#   2. creates /var/www/staging.lewissmartracing.com (owned like the live site)
#   3. nginx server block for staging:
#        - the same password gate as the live site (gate page /etc/nginx/lsr-gate/index.html,
#          password only, cookie for 30 days); you type the password, it is not stored in the repo
#        - X-Robots-Tag noindex on every response
#        - PHP with its own config (/etc/lewissmartracing-staging) and database
#          (/var/lib/lewissmartracing-staging), so staging never touches live Club32 data
#   4. Let's Encrypt certificate (until then the server's default HTTPS site answers for staging)
#   5. a deploy key generated here, added to lsr-deploy jailed to the staging directory (rrsync),
#      stored as the DEPLOY_STAGING_SSH_KEY GitHub secret
#   6. runs the Deploy workflow (which now deploys staging too)
#   7. copies the live admin users (same emails and passwords) into the staging database;
#      no orders, members or stats are copied
set -euo pipefail

# Retired (14 Nov 2026): steps 5-6 add an lsr-deploy key and a GitHub secret, which the GitHub Actions shutdown
# rules out. Staging is already set up; deploys run with deploy/deploy.sh.
echo "Retired: staging is already set up. Deploy with deploy/deploy.sh."; exit 1

HOST=78.47.73.104
SSH="ssh -t -o ControlPath=$HOME/.ssh/cm-hetzner-lsr ${LSR_SSH_KEY:+-i $LSR_SSH_KEY -o IdentitiesOnly=yes} gabriel@$HOST"
DOMAIN=staging.lewissmartracing.com
WEB=/var/www/$DOMAIN
LIVE=/var/www/lewissmartracing.com
ETC=/etc/lewissmartracing-staging
DATA=/var/lib/lewissmartracing-staging
CONF=/etc/nginx/sites-available/$DOMAIN
GATE_HTML=/etc/nginx/lsr-gate/index.html

cd "$(dirname "$0")/.."

ip=$(dig +short "$DOMAIN" A | tail -1 || true)
if [ "$ip" != "$HOST" ]; then
  echo "DNS: $DOMAIN resolves to '${ip:-nothing}', expected $HOST. Add the A record in GoDaddy (Name 'staging', Value $HOST) and run again."
  exit 1
fi
echo "DNS OK: $DOMAIN -> $HOST"
command -v gh >/dev/null || { echo "Needs the GitHub CLI (gh), logged in."; exit 1; }

read -rsp "Gate password for staging (the same one as the live site): " GATE_PASS; echo
[ -n "$GATE_PASS" ] || { echo "Password cannot be empty."; exit 1; }
case "$GATE_PASS" in *[!A-Za-z0-9._@+-]*) echo "Use letters, digits and . _ @ + - only (it goes into the nginx config)."; exit 1;; esac
TOKEN=$(openssl rand -hex 24)  # cookie value; changing it logs everyone out

KEYDIR=$(mktemp -d); trap 'rm -rf "$KEYDIR"' EXIT
ssh-keygen -q -t ed25519 -N "" -C "lsr-deploy-staging@github-actions" -f "$KEYDIR/id"
PUB=$(cat "$KEYDIR/id.pub")

# nginx server block, written here and sent base64-encoded (no quoting games over SSH).
# __SOCK__ is filled in on the server with the live site's PHP-FPM socket.
cat > "$KEYDIR/nginx.conf" <<NGINX
# staging.lewissmartracing.com: full site incl. unfinished pages. Password gate like the live site, never indexed.
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $WEB;
    index index.html index.php;

    add_header X-Robots-Tag "noindex, nofollow, noarchive" always;

    # --- password gate (same page and flow as the live site) ---
    set \$lsr_gate 0;
    if (\$cookie_lsr_staging_gate = "$TOKEN") { set \$lsr_gate 1; }

    location = /__gate.html {
        alias $GATE_HTML;
        default_type text/html;
        add_header Cache-Control "no-store" always;
        add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
    }
    location = /__gate {
        if (\$arg_p = "$GATE_PASS") {
            add_header Set-Cookie "lsr_staging_gate=$TOKEN; Path=/; Max-Age=2592000; Secure; HttpOnly; SameSite=Lax" always;
            return 302 /;
        }
        return 302 /__gate.html?wrong=1;
    }

    location ^~ /api/_lib/ { deny all; }

    location / {
        if (\$lsr_gate = 0) { return 302 /__gate.html; }
        try_files \$uri \$uri/ \$uri.html =404;
    }

    location ~ \.php\$ {
        if (\$lsr_gate = 0) { return 302 /__gate.html; }
        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param LSR_ETC $ETC;
        fastcgi_param LSR_DATA $DATA;
        fastcgi_pass __SOCK__;
    }
}
NGINX

# Copies the live admin users into the staging database (run as www-data on the server).
cat > "$KEYDIR/copy-users.php" <<'PHP'
<?php
putenv('LSR_ETC=' . $argv[1]); putenv('LSR_DATA=' . $argv[2]);
require $argv[3] . '/api/_lib/club32.php';
$live = new PDO('sqlite:/var/lib/lewissmartracing/club32.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db = db();
$n = 0;
foreach ($live->query('SELECT * FROM users') as $u) {
    $cols = array_keys($u);
    $sql = 'INSERT OR REPLACE INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $db->prepare($sql)->execute(array_values($u));
    echo '  ' . ($u['email'] ?: $u['username']) . " ({$u['role']})\n";
    $n++;
}
echo "$n admin user(s) copied to staging.\n";
PHP

CONF_B64=$(base64 -w0 "$KEYDIR/nginx.conf")
PHP_B64=$(base64 -w0 "$KEYDIR/copy-users.php")

$SSH "set -e
[ -f $GATE_HTML ] || { echo 'Gate page $GATE_HTML not found on the server.'; exit 1; }
OWNER=\$(stat -c %U $LIVE); GROUP=\$(stat -c %G $LIVE)
sudo install -d -m 755 -o \$OWNER -g \$GROUP $WEB
[ -f $WEB/index.html ] || echo '<!doctype html><title>Staging</title><p>Staging is being set up.</p>' | sudo -u \$OWNER tee $WEB/index.html >/dev/null

# separate PHP config + data for staging
sudo install -d -m 750 -o root -g www-data $ETC
[ -f /etc/lewissmartracing/bluefox.env ] && sudo cp -p /etc/lewissmartracing/bluefox.env $ETC/bluefox.env
if [ -f /etc/lewissmartracing/club32.env ]; then
  sudo sh -c 'grep -v ^SITE_URL= /etc/lewissmartracing/club32.env > $ETC/club32.env; echo SITE_URL=https://$DOMAIN >> $ETC/club32.env'
  sudo chown root:www-data $ETC/club32.env && sudo chmod 640 $ETC/club32.env
fi
sudo install -d -m 770 -o www-data -g www-data $DATA

SOCK=\$(sudo grep -rhoE 'fastcgi_pass[[:space:]]+[^;]+' /etc/nginx/sites-enabled/ | head -1 | awk '{print \$2}')
[ -n \"\$SOCK\" ] || SOCK=unix:\$(ls /run/php/php*-fpm.sock | head -1)
echo \"PHP-FPM: \$SOCK\"
echo $CONF_B64 | base64 -d | sed \"s#__SOCK__#\$SOCK#\" | sudo tee $CONF >/dev/null
sudo chmod 640 $CONF
sudo ln -sf $CONF /etc/nginx/sites-enabled/$DOMAIN
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d $DOMAIN --redirect -n || sudo certbot --nginx -d $DOMAIN --redirect
sudo nginx -t && sudo systemctl reload nginx

AK=\$(getent passwd lsr-deploy | cut -d: -f6)/.ssh/authorized_keys
sudo sed -i '\\#rrsync $WEB\$#d; \\#rrsync $WEB\"#d' \$AK
echo 'restrict,command=\"/usr/bin/rrsync $WEB\" $PUB' | sudo tee -a \$AK >/dev/null

echo '*/10 * * * * www-data LSR_ETC=$ETC LSR_DATA=$DATA php $WEB/admin/cli.php sweep >/dev/null 2>&1' | sudo tee /etc/cron.d/lewissmartracing-staging-club32 >/dev/null
sudo chmod 644 /etc/cron.d/lewissmartracing-staging-club32

echo $PHP_B64 | base64 -d | sudo tee /usr/local/lib/lsr-staging-copy-users.php >/dev/null
echo 'Server side done.'
"

gh secret set DEPLOY_STAGING_SSH_KEY < "$KEYDIR/id"
echo "GitHub secret DEPLOY_STAGING_SSH_KEY set."

gh workflow run deploy.yml
echo "Deploy started, waiting for it..."
sleep 6
gh run watch "$(gh run list --workflow deploy.yml -L 1 --json databaseId -q '.[0].databaseId')" --exit-status

# admin users (needs the PHP files, so after the deploy)
$SSH "sudo -u www-data php /usr/local/lib/lsr-staging-copy-users.php $ETC $DATA $WEB; sudo rm -f /usr/local/lib/lsr-staging-copy-users.php"

echo
echo "Checks:"
echo "  without password: $(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' https://$DOMAIN/)   (expected 302 -> /__gate.html)"
echo "  gate page:        HTTP $(curl -s -o /dev/null -w '%{http_code}' https://$DOMAIN/__gate.html)"
echo "  noindex header:   $(curl -sI https://$DOMAIN/__gate.html | grep -i x-robots-tag | tr -d '\r')"
echo "  certificate:      $(echo | openssl s_client -connect $HOST:443 -servername $DOMAIN 2>/dev/null | openssl x509 -noout -subject)"
echo "Staging: https://$DOMAIN/  (same password as the live site; admin: https://$DOMAIN/admin/ with your live login)"

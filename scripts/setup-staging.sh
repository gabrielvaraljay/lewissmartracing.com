#!/usr/bin/env bash
# One-off setup of staging.lewissmartracing.com. Run from your own machine (not CI):
#
#   bash scripts/setup-staging.sh
#
# Before running: add a DNS A record  staging.lewissmartracing.com -> 78.47.73.104  (GoDaddy).
#
# What it does:
#   1. checks the DNS record points at the server
#   2. creates /var/www/staging.lewissmartracing.com (owned like the live site)
#   3. password protects the whole site with HTTP basic auth (you choose user + password)
#   4. adds an nginx server block: noindex header on every response, PHP with its own
#      config (/etc/lewissmartracing-staging) and database (/var/lib/lewissmartracing-staging),
#      so staging never touches live Club32 data
#   5. gets a Let's Encrypt certificate (certbot --nginx)
#   6. generates a deploy key here, adds it to lsr-deploy jailed to the staging directory
#      (rrsync), and stores the private key as the DEPLOY_STAGING_SSH_KEY GitHub secret
#   7. adds the Club32 sweep cron for staging and creates the first staging admin user
#   8. runs the Deploy workflow, which now also deploys staging
set -euo pipefail

HOST=78.47.73.104
# Optional: LSR_SSH_KEY=~/.ssh/<key> to pick the key for gabriel@78.47.73.104.
SSH="ssh -t -o ControlPath=$HOME/.ssh/cm-hetzner-lsr ${LSR_SSH_KEY:+-i $LSR_SSH_KEY -o IdentitiesOnly=yes} gabriel@$HOST"
DOMAIN=staging.lewissmartracing.com
WEB=/var/www/$DOMAIN
LIVE=/var/www/lewissmartracing.com
ETC=/etc/lewissmartracing-staging
DATA=/var/lib/lewissmartracing-staging
HTPASSWD=/etc/nginx/htpasswd-$DOMAIN
CONF=/etc/nginx/sites-available/$DOMAIN

cd "$(dirname "$0")/.."

# 1. DNS
ip=$(dig +short "$DOMAIN" A | tail -1 || true)
if [ "$ip" != "$HOST" ]; then
  echo "DNS: $DOMAIN resolves to '${ip:-nothing}', expected $HOST."
  echo "Add an A record in GoDaddy: Name 'staging', Value $HOST, then run this again (it can take a few minutes)."
  exit 1
fi
echo "DNS OK: $DOMAIN -> $HOST"

command -v gh >/dev/null || { echo "Needs the GitHub CLI (gh), logged in to gabrielvaraljay/lewissmartracing.com."; exit 1; }

# 3. password (asked here, hashed on the server with openssl, never written to disk in plain text)
read -rp "Staging username [lewis]: " AUTH_USER; AUTH_USER=${AUTH_USER:-lewis}
read -rsp "Staging password: " AUTH_PASS; echo
[ -n "$AUTH_PASS" ] || { echo "Password cannot be empty."; exit 1; }
read -rp "First staging admin email (for /admin/ on staging): " ADMIN

# 6. deploy key, generated locally
KEYDIR=$(mktemp -d); trap 'rm -rf "$KEYDIR"' EXIT
ssh-keygen -q -t ed25519 -N "" -C "lsr-deploy-staging@github-actions" -f "$KEYDIR/id"
PUB=$(cat "$KEYDIR/id.pub")

$SSH "set -e
OWNER=\$(stat -c %U $LIVE); GROUP=\$(stat -c %G $LIVE)
sudo install -d -m 755 -o \$OWNER -g \$GROUP $WEB
[ -f $WEB/index.html ] || echo '<!doctype html><title>Staging</title><p>Staging is being set up.</p>' | sudo -u \$OWNER tee $WEB/index.html >/dev/null

# basic auth
printf '%s:%s\n' '$AUTH_USER' \"\$(openssl passwd -apr1 '$AUTH_PASS')\" | sudo tee $HTPASSWD >/dev/null
sudo chown root:www-data $HTPASSWD && sudo chmod 640 $HTPASSWD

# separate PHP config + data for staging
sudo install -d -m 750 -o root -g www-data $ETC
[ -f /etc/lewissmartracing/bluefox.env ] && sudo cp -p /etc/lewissmartracing/bluefox.env $ETC/bluefox.env
if [ -f /etc/lewissmartracing/club32.env ]; then
  sudo sh -c 'grep -v ^SITE_URL= /etc/lewissmartracing/club32.env > $ETC/club32.env; echo SITE_URL=https://$DOMAIN >> $ETC/club32.env'
  sudo chown root:www-data $ETC/club32.env && sudo chmod 640 $ETC/club32.env
fi
sudo install -d -m 770 -o www-data -g www-data $DATA

# nginx: same PHP-FPM socket as the live site
SOCK=\$(grep -rhoE 'fastcgi_pass[[:space:]]+[^;]+' /etc/nginx/sites-enabled/ | head -1 | awk '{print \$2}')
[ -n \"\$SOCK\" ] || SOCK=unix:\$(ls /run/php/php*-fpm.sock | head -1)
echo \"PHP-FPM: \$SOCK\"
sudo tee $CONF >/dev/null <<NGINX
# staging.lewissmartracing.com: full site incl. unfinished pages. Password protected, never indexed.
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $WEB;
    index index.html index.php;

    auth_basic \"Lewis Smart #32 staging\";
    auth_basic_user_file $HTPASSWD;
    add_header X-Robots-Tag \"noindex, nofollow, noarchive\" always;

    location ^~ /api/_lib/ { deny all; }
    location / { try_files \\\$uri \\\$uri/ \\\$uri.html =404; }
    location ~ \\.php\\\$ {
        try_files \\\$uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \\\$document_root\\\$fastcgi_script_name;
        fastcgi_param LSR_ETC $ETC;
        fastcgi_param LSR_DATA $DATA;
        fastcgi_pass \$SOCK;
    }
}
NGINX
sudo ln -sf $CONF /etc/nginx/sites-enabled/$DOMAIN
sudo nginx -t && sudo systemctl reload nginx

# TLS (certbot edits the server block and adds the http -> https redirect)
sudo certbot --nginx -d $DOMAIN --redirect -n || sudo certbot --nginx -d $DOMAIN --redirect
sudo nginx -t && sudo systemctl reload nginx

# deploy key: rsync only, jailed to the staging directory
AK=\$(getent passwd lsr-deploy | cut -d: -f6)/.ssh/authorized_keys
sudo grep -q 'rrsync $WEB' \$AK 2>/dev/null && sudo sed -i '\\#rrsync $WEB#d' \$AK
echo 'restrict,command=\"/usr/bin/rrsync $WEB\" $PUB' | sudo tee -a \$AK >/dev/null

# Club32 sweep for staging (own data dir)
echo '*/10 * * * * www-data LSR_ETC=$ETC LSR_DATA=$DATA php $WEB/admin/cli.php sweep >/dev/null 2>&1' | sudo tee /etc/cron.d/lewissmartracing-staging-club32 >/dev/null
sudo chmod 644 /etc/cron.d/lewissmartracing-staging-club32
echo 'Server side done.'
"

gh secret set DEPLOY_STAGING_SSH_KEY < "$KEYDIR/id"
echo "GitHub secret DEPLOY_STAGING_SSH_KEY set."

gh workflow run deploy.yml
echo "Deploy started. Waiting for it to finish..."
sleep 5
gh run watch "$(gh run list --workflow deploy.yml -L 1 --json databaseId -q '.[0].databaseId')" --exit-status

# first staging admin (needs the PHP files, so after the deploy)
$SSH "sudo -u www-data env LSR_ETC=$ETC LSR_DATA=$DATA php $WEB/admin/cli.php add-user '$ADMIN' developer"

echo
echo "Checks:"
echo "  without password: HTTP $(curl -s -o /dev/null -w '%{http_code}' https://$DOMAIN/)   (expected 401)"
echo "  with password:    HTTP $(curl -s -o /dev/null -w '%{http_code}' -u "$AUTH_USER:$AUTH_PASS" https://$DOMAIN/)   (expected 200)"
echo "  noindex header:   $(curl -sI -u "$AUTH_USER:$AUTH_PASS" https://$DOMAIN/ | grep -i x-robots-tag | tr -d '\r')"
echo "Staging: https://$DOMAIN/"

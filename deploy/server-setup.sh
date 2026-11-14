#!/usr/bin/env bash
# One-off server setup for deploying lewissmartracing.com from the devbox (run on the Hetzner server, 78.47.73.104):
#
#   sudo bash server-setup.sh
#
# The devbox user (key AND password, no sudo) already exists on this server: trendfingers.com's
# deploy/server-setup.sh made it. This script only does what is specific to this site, each step only if needed:
#   1. checks that devbox exists and is not in a sudo group
#   2. /var/www/lewissmartracing.com and /var/www/staging.lewissmartracing.com owned by devbox (group www-data),
#      dirs 755, files 644 (nothing in them is written by PHP: Club32 data and config live outside)
#   3. locks the old GitHub Actions user lsr-deploy: its keys were repository secrets, so they count as stolen.
#      The key file is moved aside (not deleted) and the password locked.
set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "Run it with sudo."; exit 1; }
U=devbox
OLD=lsr-deploy
STAMP="$(date +%F-%H%M)"

echo "== 1. User $U"
id "$U" > /dev/null 2>&1 || { echo "  STOP: no $U user. Run trendfingers.com's deploy/server-setup.sh first."; exit 1; }
if id -nG "$U" | tr ' ' '\n' | grep -qxE 'sudo|admin|wheel'; then echo "  STOP: $U is in a sudo group; it must not be."; exit 1; fi
echo "  exists (uid $(id -u "$U")), no sudo"

echo "== 2. Web folders owned by $U"
for d in /var/www/lewissmartracing.com /var/www/staging.lewissmartracing.com; do
  if [ ! -d "$d" ]; then echo "  $d does not exist, skipped"; continue; fi
  chown -R -h "$U:www-data" "$d"
  find "$d" -type d -print0 | xargs -0 chmod 755
  find "$d" -type f -print0 | xargs -0 chmod 644
  echo "  $d: $(find "$d" -user "$U" | wc -l) items"
done

echo "== 3. Old deploy user $OLD"
if id "$OLD" > /dev/null 2>&1; then
  OH="$(getent passwd "$OLD" | cut -d: -f6)"
  if [ -f "$OH/.ssh/authorized_keys" ]; then
    mv "$OH/.ssh/authorized_keys" "$OH/.ssh/authorized_keys.github-old-$STAMP"
    echo "  keys moved aside to $OH/.ssh/authorized_keys.github-old-$STAMP (no SSH login with them any more)"
  else
    echo "  no authorized_keys (already locked)"
  fi
  usermod -L "$OLD" && echo "  password locked"
  echo "  last login: $(lastlog -u "$OLD" 2>/dev/null | tail -1 | awk '{$1=""; print}' | sed 's/^ *//')"
else
  echo "  no $OLD user, nothing to lock"
fi

echo
echo "Done. From the devbox: deploy/deploy.sh --dry-run (asks for the key's passphrase, then the $U password)."

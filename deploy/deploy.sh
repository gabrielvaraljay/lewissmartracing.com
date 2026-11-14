#!/usr/bin/env bash
# Deploys lewissmartracing.com (live) and staging.lewissmartracing.com from the devbox, without GitHub Actions
# (switched off on 13 Nov 2026 after the token theft: a workflow could read the server key from the repository
# secrets). The deploy key lives only on the devbox, so GitHub holds no secrets.
#
#   deploy/deploy.sh                  live + staging (as the old workflow did): shows what would change, then asks
#                                     for the confirmation word before anything goes up
#   deploy/deploy.sh live|staging     only that one (staging needs no confirmation word)
#   deploy/deploy.sh ... --dry-run    only shows what would change (still logs in once), uploads nothing
#
# It builds the last commit (git archive HEAD, then npm ci + build in a temporary folder), never uncommitted files,
# and stops when GitHub has commits that are not here: someone else pushed, so look at them first.
# Settings, outside the repo: ~/.config/lewissmartracing/deploy.env (DEPLOY_HOST, DEPLOY_USER, DEPLOY_KEY).
# The server side (web folders owned by devbox, old lsr-deploy keys locked) is set up once with deploy/server-setup.sh.
set -euo pipefail

TARGET=all; DRY=0
for a in "$@"; do
  case "$a" in
    all|live|staging) TARGET="$a" ;;
    --dry-run) DRY=1 ;;
    *) echo "Usage: deploy/deploy.sh [all|live|staging] [--dry-run]"; exit 1 ;;
  esac
done

ROOT="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
cd "$ROOT"
CONF="$HOME/.config/lewissmartracing/deploy.env"
[ -f "$CONF" ] || { echo "Missing $CONF (DEPLOY_HOST, DEPLOY_USER, DEPLOY_KEY)"; exit 1; }
# shellcheck disable=SC1090
source "$CONF"
: "${DEPLOY_HOST:?not set in $CONF}" "${DEPLOY_USER:?not set in $CONF}" "${DEPLOY_KEY:?not set in $CONF}"
DEPLOY_KEY="${DEPLOY_KEY/#\~/$HOME}"
[ -f "$DEPLOY_KEY" ] || { echo "The deploy key $DEPLOY_KEY is missing"; exit 1; }

BRANCH=main
LIVE_DIR=/var/www/lewissmartracing.com/
STAGING_DIR=/var/www/staging.lewissmartracing.com/
LIVE_DOMAIN=lewissmartracing.com
STAGING_DOMAIN=staging.lewissmartracing.com
fail() { echo "STOP: $*"; exit 1; }
want() { [ "$TARGET" = all ] || [ "$TARGET" = "$1" ]; }

# ---- Safety checks: the right branch, nothing uncommitted, nothing unknown on GitHub
[ "$(git branch --show-current)" = "$BRANCH" ] || fail "the deploy goes from $BRANCH; you are on $(git branch --show-current)."
git diff --quiet HEAD -- || fail "uncommitted changes. Commit them first (the deploy takes the last commit)."
git fetch -q origin "$BRANCH" || fail "could not reach GitHub to compare."
UNKNOWN="$(git log --oneline "HEAD..origin/$BRANCH")"
if [ -n "$UNKNOWN" ]; then
  echo "GitHub has commits on $BRANCH that are not here:"; echo "$UNKNOWN"
  fail "look at them first (git log -p HEAD..origin/$BRANCH). Merge only what you recognise."
fi
AHEAD="$(git rev-list --count "origin/$BRANCH..HEAD")"
REV="$(git rev-parse --short HEAD)"

TMP="$(mktemp -d)"
SERVER="$DEPLOY_USER@$DEPLOY_HOST"
CTL="$TMP/ssh.ctl"
trap 'ssh -o ControlPath="$CTL" -O exit "$SERVER" > /dev/null 2>&1; rm -rf "${TMP:-}"' EXIT

# ---- Build the commit (not the working folder) in a temporary copy
echo "== Building $BRANCH $REV ($TARGET)"
mkdir -p "$TMP/src"
git archive HEAD | tar -x -C "$TMP/src"
( cd "$TMP/src" && npm ci --prefer-offline --no-audit --no-fund --loglevel=error > /dev/null ) || fail "npm ci failed."
if want live; then
  ( cd "$TMP/src" && npm run -s build ) || fail "the production build failed."
  mv "$TMP/src/dist" "$TMP/live"
fi
if want staging; then
  ( cd "$TMP/src" && npm run -s build:staging ) || fail "the staging build failed."
  mv "$TMP/src/dist" "$TMP/staging"
fi
for d in live staging; do
  [ -d "$TMP/$d" ] || continue
  [ -f "$TMP/$d/index.html" ] || fail "the $d build has no index.html."
  find "$TMP/$d" -name '.env*' | grep -q . && fail "an .env file is in the $d build; it must never be uploaded."
  find "$TMP/$d" -name '.DS_Store' -delete
done

echo "Deploying $BRANCH $REV. Not on GitHub yet: $AHEAD commit(s)."

# ---- SSH: the key that lives only on the devbox, host key checked against ~/.ssh/known_hosts.
# The server wants the key AND the devbox user's password, so the first connection asks for the key's passphrase
# and then the password; every later step reuses that one connection, which closes when the script ends.
echo "Connecting to $SERVER: the deploy key's passphrase, then the $DEPLOY_USER user's server password."
ssh -i "$DEPLOY_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o ControlMaster=yes -o ControlPath="$CTL" \
  -o ControlPersist=yes -fN "$SERVER" || fail "could not log in to $DEPLOY_HOST."
SSH_OPTS="-o ControlPath=$CTL -o ControlMaster=no -o BatchMode=yes -o StrictHostKeyChecking=yes"
remote() { ssh $SSH_OPTS "$SERVER" "$@"; }

# Server-only, never touched: certbot's .well-known/. (The gate page, PHP config and Club32 data are outside the
# web folders: /etc/lewissmartracing*, /var/lib/lewissmartracing*.)
RSYNC=(rsync -rlptz --checksum --delete --chmod=D755,F644 --filter='P .well-known/' -e "ssh $SSH_OPTS")

# ---- Preview: what would change, per site. The full lists are kept on the devbox for reading.
PLAN_DIR="$HOME/.cache/lewissmartracing-deploy"; mkdir -p "$PLAN_DIR"
preview() {   # $1 = live|staging, $2 = server folder
  remote "test -w '$2' && test -w '${2}index.html'" \
    || fail "$2 is not writable by $DEPLOY_USER (run deploy/server-setup.sh on the server first)."
  local plan="$PLAN_DIR/plan-$1-$(date +%F-%H%M%S)-$REV.txt" dels changes
  "${RSYNC[@]}" --dry-run --itemize-changes "$TMP/$1/" "$SERVER:$2" > "$plan"
  dels="$(grep -c '^\*deleting' "$plan" || true)"
  changes="$(grep -vc '^\*deleting' "$plan" || true)"
  echo "== $1 ($2): would upload or update $changes item(s), delete $dels item(s). Full list: $plan"
  if [ "$dels" -gt 0 ]; then
    awk '/^\*deleting/ { p=$2; sub(/\/.*/, "/", p); n[p]++ } END { for (k in n) printf "  - %s (%d)\n", k, n[k] }' "$plan" | sort
  fi
}
want live && preview live "$LIVE_DIR"
want staging && preview staging "$STAGING_DIR"
if [ "$DRY" = 1 ]; then echo "Dry run: nothing uploaded."; exit 0; fi

if want live; then
  read -rp "Type deploy-live to publish: " word
  [ "$word" = deploy-live ] || fail "confirmation word not entered."
fi

upload() {   # $1 = live|staging, $2 = server folder
  echo "== Uploading $1"
  "${RSYNC[@]}" --stats "$TMP/$1/" "$SERVER:$2" \
    | { grep -E "^(Number of (regular )?files transferred|Number of deleted files|Total transferred file size)" || true; }
}

# ---- Checks, against this server's IP (not whatever DNS says)
FAILED=0
probe() { curl -sS -o /dev/null -w "$1" --max-time 20 --retry 2 --retry-all-errors --resolve "$2:443:$DEPLOY_HOST" "https://$2${3:-/}" || true; }

if want live; then
  upload live "$LIVE_DIR"
  # While the temporary password gate is on, / redirects (302) to /__gate.html.
  code="$(probe '%{http_code}' "$LIVE_DOMAIN")"
  echo "  https://$LIVE_DOMAIN/ -> $code (expected 200, or 302 while the gate is on)"
  [ "$code" = 200 ] || [ "$code" = 302 ] || FAILED=1
fi
if want staging; then
  upload staging "$STAGING_DIR"
  # Without the gate cookie staging must redirect to the password page, never answer 200.
  out="$(probe '%{http_code} %{redirect_url}' "$STAGING_DOMAIN")"
  echo "  https://$STAGING_DOMAIN/ -> $out (expected 302 to /__gate.html)"
  case "$out" in "302 "*/__gate.html) ;; *) FAILED=1 ;; esac
fi

if [ "$FAILED" = 1 ]; then echo "DEPLOYED, BUT A CHECK FAILED: see the codes above."; exit 1; fi
echo "Done: $TARGET is on $REV."
[ "$AHEAD" = 0 ] || echo "Remember: $AHEAD commit(s) are not on GitHub yet (git push origin $BRANCH)."

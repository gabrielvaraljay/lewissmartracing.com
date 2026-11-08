#!/usr/bin/env bash
# Asks for the BlueFox API key once (hidden input) and stores it in two places:
#   1. the local Claude Code MCP config (local scope, not in the repo)
#   2. /etc/lewissmartracing/bluefox.env on the server, readable only by root and php-fpm
# The key is never written into the repository.
set -euo pipefail
PROJECT_ID=6af0994322a3fca8ec780b50
SSH="ssh -o ControlPath=$HOME/.ssh/cm-hetzner-lsr gabriel@78.47.73.104"

read -rsp "BlueFox API key: " KEY; echo
[ -n "$KEY" ] || { echo "Empty key, aborting."; exit 1; }

claude mcp remove bluefox-email -s local >/dev/null 2>&1 || true
claude mcp add bluefox-email -s local \
  -e BLUEFOX_BASE_URL=https://api.bluefox.email \
  -e BLUEFOX_PROJECT_ID=$PROJECT_ID \
  -e BLUEFOX_API_KEY="$KEY" \
  -- npx -y @bluefox-email/mcp@latest
echo "MCP server added (local scope)."

printf 'BLUEFOX_API_KEY=%s\nBLUEFOX_PROJECT_ID=%s\n' "$KEY" "$PROJECT_ID" | $SSH 'sudo install -d -m 750 -o root -g www-data /etc/lewissmartracing && sudo tee /etc/lewissmartracing/bluefox.env >/dev/null && sudo chown root:www-data /etc/lewissmartracing/bluefox.env && sudo chmod 640 /etc/lewissmartracing/bluefox.env && echo "Server key stored."'

STATUS=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $KEY" "https://api.bluefox.email/v1/projectId/$PROJECT_ID/contacts?limit=1")
echo "BlueFox API check: HTTP $STATUS (200 = key works)"

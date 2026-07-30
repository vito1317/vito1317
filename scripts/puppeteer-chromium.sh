#!/usr/bin/env bash
# Wrapper used as PUPPETEER_EXECUTABLE_PATH during `vite build` prerendering.
# vite-plugin-prerenderer hardcodes the Puppeteer renderer options and does not
# forward launch args, so we force --no-sandbox here (build often runs as root).
set -euo pipefail

for candidate in \
  "${CHROMIUM_BIN:-}" \
  /usr/bin/chromium-browser \
  /usr/bin/chromium \
  /snap/bin/chromium \
  /usr/bin/google-chrome-stable \
  /usr/bin/google-chrome; do
  if [ -n "$candidate" ] && command -v "$candidate" >/dev/null 2>&1; then
    exec "$candidate" --no-sandbox --disable-setuid-sandbox "$@"
  fi
done

echo "puppeteer-chromium.sh: no Chromium/Chrome binary found" >&2
exit 1

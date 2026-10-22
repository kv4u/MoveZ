#!/usr/bin/env bash
# Assembles the static website into site-dist/ from its single sources:
#   site/                 pages, styles, script
#   branding/             icon, favicon, social card
#   docs/screenshots/     app screenshots (shared with the README)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="$root/site-dist"

rm -rf "$out"
mkdir -p "$out/assets" "$out/screenshots"

cp "$root"/site/*.html "$root"/site/*.css "$root"/site/*.js "$out/"
cp "$root/branding/icon.svg" "$root/branding/icon.ico" "$root/branding/social-card.png" "$out/assets/"
mv "$out/assets/icon.ico" "$out/assets/favicon.ico"
cp "$root/branding/png/icon-180.png" "$out/assets/apple-touch-icon.png"
cp "$root"/docs/screenshots/*.png "$out/screenshots/"
touch "$out/.nojekyll"

echo "Site built in $out"

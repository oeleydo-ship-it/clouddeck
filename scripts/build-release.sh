#!/usr/bin/env bash
# Build a CodeCanyon-ready zip of Uplary (source only — buyers run composer/npm on the server).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-1.0.0}"
STAMP="$(date +%Y%m%d)"
OUT_DIR="${ROOT}/dist"
ARCHIVE="${OUT_DIR}/uplary-${VERSION}-${STAMP}.zip"
STAGING="${OUT_DIR}/.staging-uplary"

rm -rf "${STAGING}"
mkdir -p "${STAGING}" "${OUT_DIR}"

echo "Copying application files..."
rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.cursor/' \
  --exclude='.claude/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='dist/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='!.env.example' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='storage/app/private/*' \
  --exclude='storage/app/public/*' \
  --exclude='public/build/' \
  --exclude='public/hot' \
  --exclude='*.zip' \
  --exclude='.phpunit.cache/' \
  --exclude='.phpunit.result.cache' \
  --exclude='coverage/' \
  "${ROOT}/" "${STAGING}/uplary/"

# Preserve empty storage directories
mkdir -p \
  "${STAGING}/uplary/storage/logs" \
  "${STAGING}/uplary/storage/framework/cache/data" \
  "${STAGING}/uplary/storage/framework/sessions" \
  "${STAGING}/uplary/storage/framework/views" \
  "${STAGING}/uplary/storage/app/private" \
  "${STAGING}/uplary/storage/app/public" \
  "${STAGING}/uplary/bootstrap/cache"

touch "${STAGING}/uplary/storage/logs/.gitkeep"
touch "${STAGING}/uplary/storage/app/public/.gitkeep"

echo "Writing VERSION file..."
echo "${VERSION}" > "${STAGING}/uplary/VERSION"

echo "Creating ${ARCHIVE}..."
rm -f "${ARCHIVE}"
(cd "${STAGING}" && zip -rq "${ARCHIVE}" uplary)

rm -rf "${STAGING}"

BYTES="$(wc -c < "${ARCHIVE}" | tr -d ' ')"
echo "Done: ${ARCHIVE} (${BYTES} bytes)"
echo ""
echo "Buyers should:"
echo "  1. Unzip and point the web root to uplary/public"
echo "  2. cp .env.example .env && php artisan key:generate"
echo "  3. composer install --no-dev && npm ci && npm run build"
echo "  4. php artisan migrate && visit /install"
echo "  See INSTALLATION.md for production Horizon, Redis, and Reverb."

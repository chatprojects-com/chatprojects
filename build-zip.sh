#!/usr/bin/env bash
# Build a WordPress.org-ready ZIP (POSIX equivalent of build-zip.ps1).
# Usage: ./build-zip.sh [output.zip]   (default: ../chatprojects.zip)
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="chatprojects"
OUT="${1:-$PLUGIN_DIR/../chatprojects.zip}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "Building production assets..."
( cd "$PLUGIN_DIR" && npm run build --silent && npm run build:widget --silent )

echo "Staging files (honouring .distignore)..."
mkdir -p "$STAGE/$PLUGIN_NAME"
rsync -a \
	--exclude-from="$PLUGIN_DIR/.distignore" \
	--exclude='build-zip.sh' \
	--exclude='*.zip' \
	"$PLUGIN_DIR/" "$STAGE/$PLUGIN_NAME/"

# Belt and braces: never ship PHP from the asset tree, and drop any empty folders.
find "$STAGE/$PLUGIN_NAME/assets/dist" -name '*.php' -delete 2>/dev/null || true
find "$STAGE/$PLUGIN_NAME" -type d -empty -delete

rm -f "$OUT"
( cd "$STAGE" && zip -qr "$OUT" "$PLUGIN_NAME" )
echo "Created $(realpath "$OUT") ($(du -h "$OUT" | cut -f1))"

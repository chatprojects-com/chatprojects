#!/usr/bin/env bash
# Prepare a WordPress.org SVN release of ChatProjects (Free).
#
#   ./svn-prepare.sh [svn working copy]     (default: ~/svn/chatprojects)
#
# Builds the release zip (build-zip.sh), checks the version numbers agree,
# checks out (or updates) plugins.svn.wordpress.org/chatprojects anonymously,
# syncs trunk from the zip and stages additions/removals. It never commits:
# it prints the commit and tag commands for you to run with your WP.org
# credentials.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVN_URL="https://plugins.svn.wordpress.org/chatprojects"
WC="${1:-$HOME/svn/chatprojects}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

command -v svn >/dev/null || { echo "svn is not installed (Fedora: sudo dnf install subversion)."; exit 1; }

# Versions must agree: plugin header, readme Stable tag, CHATPROJECTS_VERSION.
HEADER_VERSION="$(sed -n 's/^ \* Version: *//p' "$PLUGIN_DIR/chatprojects.php" | head -1 | tr -d '\r')"
CONST_VERSION="$(sed -n "s/^define('CHATPROJECTS_VERSION', '\([^']*\)');/\1/p" "$PLUGIN_DIR/chatprojects.php" | head -1)"
STABLE_TAG="$(sed -n 's/^Stable tag: *//p' "$PLUGIN_DIR/readme.txt" | head -1 | tr -d '\r')"
if [[ -z "$HEADER_VERSION" || "$HEADER_VERSION" != "$CONST_VERSION" || "$HEADER_VERSION" != "$STABLE_TAG" ]]; then
	echo "Version mismatch: header=$HEADER_VERSION constant=$CONST_VERSION stable_tag=$STABLE_TAG"
	exit 1
fi
VERSION="$HEADER_VERSION"
grep -q "^= $VERSION =" "$PLUGIN_DIR/readme.txt" || { echo "readme.txt has no changelog entry for $VERSION"; exit 1; }

echo "Building release zip for $VERSION..."
"$PLUGIN_DIR/build-zip.sh" "$STAGE/chatprojects.zip" >/dev/null
unzip -q "$STAGE/chatprojects.zip" -d "$STAGE"

# Sparse checkout: trunk and assets in full, tags as a listing only.
if [[ -d "$WC/.svn" ]]; then
	svn update --quiet "$WC/trunk" "$WC/assets"
	svn update --quiet --set-depth immediates "$WC/tags"
else
	mkdir -p "$(dirname "$WC")"
	svn checkout --quiet --depth immediates "$SVN_URL" "$WC"
	svn update --quiet --set-depth infinity "$WC/trunk" "$WC/assets"
fi

if svn ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1; then
	echo "tags/$VERSION already exists on WordPress.org; bump the version first."
	exit 1
fi

echo "Syncing trunk..."
rsync -a --delete --exclude='.svn' "$STAGE/chatprojects/" "$WC/trunk/"

# Stage new and removed files.
svn status "$WC/trunk" | awk '/^\?/ {print substr($0, 9)}' | while IFS= read -r f; do svn add --quiet --parents "$f"; done
svn status "$WC/trunk" | awk '/^!/ {print substr($0, 9)}' | while IFS= read -r f; do svn rm --quiet "$f"; done

echo
svn status "$WC/trunk" | awk '{print $1}' | sort | uniq -c | sed 's/^/  /'
echo
echo "Review:   svn status $WC/trunk   |   svn diff $WC/trunk/readme.txt"
echo "Release (needs your WordPress.org username/password):"
echo "  svn commit $WC/trunk -m \"Release $VERSION\""
echo "  svn copy $SVN_URL/trunk $SVN_URL/tags/$VERSION -m \"Tag $VERSION\""
echo
echo "WordPress.org serves the new version once tags/$VERSION exists and readme.txt says Stable tag: $VERSION (CDN caches for up to a few hours)."

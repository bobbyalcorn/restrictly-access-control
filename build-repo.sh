#!/usr/bin/env bash
set -e

# GitHub/plugin folder slug
PLUGIN_SLUG="restrictly-access-control"

# Extract version from main plugin file
VERSION=$(
  grep -m1 -E '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[0-9]+\.[0-9]+(\.[0-9]+)?' restrictly-access-control.php \
  | sed -E 's/.*Version:[[:space:]]*([0-9]+(\.[0-9]+){1,2}).*/\1/'
)

# Fallback: Stable tag in readme.txt
if [ -z "$VERSION" ]; then
  VERSION=$(grep -m1 -E '^Stable tag:[[:space:]]*[0-9]+\.[0-9]+(\.[0-9]+)?' readme.txt \
    | sed -E 's/.*:[[:space:]]*([0-9]+(\.[0-9]+){1,2}).*/\1/')
fi

# Safety net
: "${VERSION:=0.0.0-dev}"

BUILD_DIR="dist/${PLUGIN_SLUG}-repo"
TRUNK_DIR="${BUILD_DIR}/trunk"
ASSETS_DIR="${BUILD_DIR}/assets"

echo "Building WordPress.org repository package for ${PLUGIN_SLUG} v${VERSION}..."

# Reset build folder
rm -rf dist
mkdir -p "${TRUNK_DIR}"
mkdir -p "${ASSETS_DIR}"

# Copy plugin files into trunk/
rsync -av ./ "${TRUNK_DIR}" \
  --exclude "vendor/*" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude ".git" \
  --exclude ".github" \
  --exclude ".vscode" \
  --exclude ".idea" \
  --exclude "tests" \
  --exclude "dist" \
  --exclude "scripts" \
  --exclude "npm" \
  --exclude "branding" \
  --exclude "build*.sh" \
  --exclude "composer.*" \
  --exclude "package*.json" \
  --exclude "phpcs*" \
  --exclude "phpstan*" \
  --exclude "eslint*" \
  --exclude ".eslintrc*" \
  --exclude "stylelint*" \
  --exclude ".stylelint*" \
  --exclude ".jshintrc" \
  --exclude ".prettier*" \
  --exclude "webpack.config.js" \
  --exclude "vite.config.js" \
  --exclude "tailwind.config.js" \
  --exclude "postcss.config.js" \
  --exclude "tsconfig.json" \
  --exclude "*.md" \
  --exclude ".gitattributes" \
  --exclude ".gitignore" \
  --exclude ".distignore" \
  --exclude "*.log" \
  --exclude "*.phar" \
  --exclude "TESTING.md"

# Copy branding assets to /assets (required by WordPress.org)
cp -r branding/banner-*.png "${ASSETS_DIR}/" 2>/dev/null || true
cp -r branding/icon-*.png "${ASSETS_DIR}/" 2>/dev/null || true
cp -r branding/icon.svg "${ASSETS_DIR}/" 2>/dev/null || true
cp -r branding/screenshot-*.png "${ASSETS_DIR}/" 2>/dev/null || true

# Clean OS artifacts
rm -f "${TRUNK_DIR}/object_id" "${TRUNK_DIR}/object_id," || true
rm -f "${TRUNK_DIR}/Thumbs.db" "${TRUNK_DIR}/.DS_Store" || true

echo "Done. Repo package ready at dist/${PLUGIN_SLUG}-repo/"
echo "Version: ${VERSION}"
echo "trunk/: production plugin"
echo "assets/: icons, banners, screenshots"
echo "Ready for WordPress.org SVN deployment."

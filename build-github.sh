#!/usr/bin/env bash
set -e

PLUGIN_SLUG="restrictly-access-control"

# 🔍 Robust version extraction from plugin header
VERSION=$(
  grep -m1 -E '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[0-9]+\.[0-9]+(\.[0-9]+)?' restrictly-access-control.php \
  | sed -E 's/.*Version:[[:space:]]*([0-9]+(\.[0-9]+){1,2}).*/\1/'
)

# 🧩 Fallback: use Stable tag from readme.txt if header missing
if [ -z "$VERSION" ]; then
  VERSION=$(grep -m1 -E '^Stable tag:[[:space:]]*[0-9]+\.[0-9]+(\.[0-9]+)?' readme.txt \
    | sed -E 's/.*:[[:space:]]*([0-9]+(\.[0-9]+){1,2}).*/\1/')
fi

# 🛡️ Final safety net
: "${VERSION:=0.0.0-dev}"

BUILD_DIR="dist/${PLUGIN_SLUG}-github"
ZIP_NAME="${PLUGIN_SLUG}-github-${VERSION}.zip"

echo "🚀 Building GitHub release package for ${PLUGIN_SLUG} v${VERSION}..."

# 🧹 Cleanup old build
rm -rf dist
mkdir -p "${BUILD_DIR}"

# 📦 Copy plugin source (excluding dev cruft)
rsync -av ./ "${BUILD_DIR}" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude ".git" \
  --exclude ".github" \
  --exclude ".vscode" \
  --exclude ".idea" \
  --exclude "tests" \
  --exclude "dist" \
  --exclude "scripts" \
  --exclude "*.cache" \
  --exclude "*.log" \
  --exclude "*.phar"

# 🖼️ Include documentation and screenshots from branding/
mkdir -p "${BUILD_DIR}/branding"
cp -r branding/screenshot-*.png "${BUILD_DIR}/branding/" 2>/dev/null || true
cp -r README.md "${BUILD_DIR}/" 2>/dev/null || true
cp -r CHANGELOG.md "${BUILD_DIR}/" 2>/dev/null || true

# 🧼 Remove system artifacts
rm -f "${BUILD_DIR}/object_id" "${BUILD_DIR}/object_id," || true
rm -f "${BUILD_DIR}/Thumbs.db" "${BUILD_DIR}/.DS_Store" || true

# 🗜️ Create versioned ZIP (root files, no nested folder)
cd dist || exit
cd "${PLUGIN_SLUG}-github" || exit
zip -rq "../${ZIP_NAME}" ./*
cd ../..

echo "✅ Done! GitHub release package created: dist/${ZIP_NAME}"
echo "📦 Version: ${VERSION}"
echo "🧩 Includes README.md, CHANGELOG.md, and branding screenshots"
echo "🚀 Ideal for GitHub releases and development distribution"

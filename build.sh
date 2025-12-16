#!/usr/bin/env bash
set -e

PLUGIN_SLUG="restrictly-access-control"

# 🔍 Robust version extraction
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

BUILD_DIR="dist/${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "🚀 Building clean package for ${PLUGIN_SLUG} v${VERSION}..."

# 🧹 Cleanup old build
rm -rf dist
mkdir -p "${BUILD_DIR}"

# 📦 Copy plugin files (excluding dev/config junk)
rsync -av ./ "${BUILD_DIR}" --exclude-from=.distignore --exclude "dist"

# 🧼 Extra cleanup just in case
rm -rf "${BUILD_DIR}/node_modules" \
       "${BUILD_DIR}/vendor" \
       "${BUILD_DIR}/branding" \
       "${BUILD_DIR}/.git" \
       "${BUILD_DIR}/.github" \
       "${BUILD_DIR}/.vscode" \
       "${BUILD_DIR}/.idea" \
       "${BUILD_DIR}/tests" \
       "${BUILD_DIR}/scripts" \
       "${BUILD_DIR}/dist" \
       "${BUILD_DIR}/phpcs*" \
       "${BUILD_DIR}/eslint*" \
       "${BUILD_DIR}/stylelint*" \
       "${BUILD_DIR}/webpack.config.js" \
       "${BUILD_DIR}/vite.config.js" \
       "${BUILD_DIR}/tailwind.config.js" \
       "${BUILD_DIR}/postcss.config.js" \
       "${BUILD_DIR}/tsconfig.json" \
       "${BUILD_DIR}/*.log" \
       "${BUILD_DIR}/*.phar" || true

# 🧹 Remove system artifacts
rm -f "${BUILD_DIR}/object_id" "${BUILD_DIR}/object_id," || true
rm -f "${BUILD_DIR}/Thumbs.db" "${BUILD_DIR}/.DS_Store" || true

# 🗜️ Create the ZIP file (versioned)
cd dist || exit
zip -rq "${ZIP_NAME}" "${PLUGIN_SLUG}"

echo "✅ Done! Created dist/${ZIP_NAME}"
echo "📦 Version: ${VERSION}"
echo "🧱 Clean plugin build generated successfully!"

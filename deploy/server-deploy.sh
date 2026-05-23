#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/dommood.com.ua"
REPO="git@github.com:ignatyuk71/dommood.com.ua.git"
BRANCH="main"
TS="$(date +%F_%H%M%S)"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
RELEASE_DIR="$APP_DIR/releases/$TS"
DEPLOY_UID="$(id -u)"

make_writable_for_deploy() {
  for path in "$@"; do
    [ -e "$path" ] || continue
    find "$path" -type d -user "$DEPLOY_UID" -exec chmod 2775 {} + 2>/dev/null || true
    find "$path" -type f -user "$DEPLOY_UID" -exec chmod ug+rw {} + 2>/dev/null || true
  done
}

mkdir -p "$APP_DIR/releases" "$APP_DIR/shared"
mkdir -p "$APP_DIR/shared/storage/app/public"
mkdir -p "$APP_DIR/shared/storage/app/public/products"
mkdir -p "$APP_DIR/shared/storage/framework/cache/data"
mkdir -p "$APP_DIR/shared/storage/framework/sessions"
mkdir -p "$APP_DIR/shared/storage/framework/views"
mkdir -p "$APP_DIR/shared/storage/logs"
mkdir -p "$APP_DIR/shared/bootstrap/cache"

cd "$APP_DIR"
git clone -b "$BRANCH" "$REPO" "$RELEASE_DIR"

ln -sfn "$APP_DIR/shared/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$APP_DIR/shared/storage" "$RELEASE_DIR/storage"
rm -rf "$RELEASE_DIR/bootstrap/cache"
ln -sfn "$APP_DIR/shared/bootstrap/cache" "$RELEASE_DIR/bootstrap/cache"

make_writable_for_deploy "$APP_DIR/shared/storage" "$APP_DIR/shared/bootstrap/cache"

cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

php artisan optimize:clear
php artisan migrate --force

rm -rf public/storage
php artisan storage:link

LEGACY_BANNER_DIR="$APP_DIR/shared/storage/app/public/banners/featured"
mkdir -p "$LEGACY_BANNER_DIR"
if [ -f "$RELEASE_DIR/public/brand/home/pajamas-promo-img-4461.webp" ]; then
  cp "$RELEASE_DIR/public/brand/home/pajamas-promo-img-4461.webp" "$LEGACY_BANNER_DIR/pajamas-promo-user-attachment.webp"
  chmod a+r "$LEGACY_BANNER_DIR/pajamas-promo-user-attachment.webp"
fi

if [ ! -d "$APP_DIR/shared/build" ]; then
  echo "DEPLOY_FAILED: public/build не завантажений у $APP_DIR/shared/build"
  exit 1
fi

rm -rf "$RELEASE_DIR/public/build"
mkdir -p "$RELEASE_DIR/public/build"
cp -a "$APP_DIR/shared/build/." "$RELEASE_DIR/public/build/"
chmod -R a+rX "$RELEASE_DIR/public/build"
rm -rf "$APP_DIR/shared/build"

make_writable_for_deploy "$APP_DIR/shared/storage" "$APP_DIR/shared/bootstrap/cache"

ln -sfn "$RELEASE_DIR" "$APP_DIR/current"

if ! sudo -n /usr/bin/systemctl reload php8.4-fpm; then
  echo "DEPLOY_FAILED: php-fpm reload не виконано, opcache може віддавати старий release"
  exit 1
fi

cd "$APP_DIR/releases"
ls -1dt */ | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf

echo "DEPLOY_OK: $TS"

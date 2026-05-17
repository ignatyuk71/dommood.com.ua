#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/dommood.com.ua"
REPO="git@github.com:ignatyuk71/dommood.com.ua.git"
BRANCH="main"
TS="$(date +%F_%H%M%S)"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
RELEASE_DIR="$APP_DIR/releases/$TS"

mkdir -p "$APP_DIR/releases" "$APP_DIR/shared"
mkdir -p "$APP_DIR/shared/storage/app/public"
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

chmod -R ug+rwX "$APP_DIR/shared/storage" "$APP_DIR/shared/bootstrap/cache"

cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

php artisan optimize:clear
php artisan migrate --force

rm -rf public/storage
php artisan storage:link

if [ ! -d "$APP_DIR/shared/build" ]; then
  echo "DEPLOY_FAILED: public/build не завантажений у $APP_DIR/shared/build"
  exit 1
fi

rm -rf "$RELEASE_DIR/public/build"
cp -R "$APP_DIR/shared/build" "$RELEASE_DIR/public/build"
chmod -R a+rX "$RELEASE_DIR/public/build"
rm -rf "$APP_DIR/shared/build"

chmod -R ug+rwX "$APP_DIR/shared/storage" "$APP_DIR/shared/bootstrap/cache"

ln -sfn "$RELEASE_DIR" "$APP_DIR/current"

if command -v systemctl >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
  sudo -n /usr/bin/systemctl reload php8.4-fpm
else
  echo "DEPLOY_WARN: php-fpm reload пропущено, бо deploy не має passwordless sudo"
fi

cd "$APP_DIR/releases"
ls -1dt */ | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf

echo "DEPLOY_OK: $TS"

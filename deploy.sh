#!/bin/bash
set -e

BASE_DIR=/var/www/gps-dashboard

cd $BASE_DIR

git fetch origin
git reset --hard origin/main
git clean -fd

cd $BASE_DIR/gps-core
mkdir -p storage/logs bootstrap/cache
sudo chown -R gpsroot:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache || true

composer install --no-dev --optimize-autoloader
php artisan optimize:clear

cd $BASE_DIR/gps-fleet
sudo rm -rf dist
npm install
npm run build
sudo chown -R gpsroot:nginx dist
sudo chmod -R 755 dist
sudo chcon -R -t httpd_sys_content_t dist || true

sudo systemctl reload php-fpm
sudo systemctl reload nginx

echo "Deploy complete"
#!/usr/bin/env bash
set -e

echo "Deploy gps-core..."
cd /var/www/gps-dashboard/gps-core
git pull
composer install --no-dev --optimize-autoloader
sudo chown -R apache:apache storage bootstrap/cache
sudo -u apache php artisan optimize:clear
sudo -u apache php artisan optimize
sudo systemctl restart php-fpm

echo "Deploy gps-fleet..."
cd /var/www/gps-dashboard/gps-fleet
git pull
npm install
npm run build
sudo chown -R nginx:nginx dist

sudo systemctl reload nginx

echo "Done."
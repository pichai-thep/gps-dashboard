#!/bin/bash

set -e

BASE_DIR=/var/www/gps-dashboard

echo "Deploy gps-core..."
cd $BASE_DIR
git fetch origin
git reset --hard origin/main
git clean -fd

echo "Build gps-core..."
cd gps-core
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
sudo systemctl reload php-fpm || true

echo "Build gps-fleet..."
cd $BASE_DIR/gps-fleet

npm install
npm run build

echo "Deploy complete"
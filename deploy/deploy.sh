#!/bin/bash
# Mercury SaaS - Deployment Script
# Usage: ./deploy.sh

set -e

APP_DIR="/var/www/mercury"
BRANCH="main"

echo "==> Mercury SaaS Deployment"
echo "==> $(date)"

cd "$APP_DIR"

# Pull latest code
echo "==> Pulling latest changes..."
git pull origin "$BRANCH"

# Install PHP dependencies
echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
echo "==> Building frontend assets..."
npm ci --production=false
npm run build

# Run central database migrations
echo "==> Running central migrations..."
php artisan migrate --force

# Run tenant database migrations
echo "==> Running tenant migrations..."
php artisan tenants:migrate --force

# Clear and rebuild caches
echo "==> Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache 2>/dev/null || true

# Restart queue workers
echo "==> Restarting queue workers..."
php artisan queue:restart

# Restart PHP-FPM
echo "==> Restarting PHP-FPM..."
sudo systemctl reload php8.4-fpm

echo "==> Deployment complete!"
echo "==> $(date)"

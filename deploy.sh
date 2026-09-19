#!/usr/bin/env bash
# ==============================================================================
# LINDR PRODUCTION DEPLOYMENT SCRIPT
# Controlled, non-destructive automated deployment pipeline
# ==============================================================================

set -e

echo "🚀 Starting Lindr Production Deployment..."

# 1. Verify working environment and .env existence
if [ ! -f ".env" ]; then
    echo "❌ Error: Production .env file is missing in the application root!"
    exit 1
fi

# 2. Pull latest approved code from Git
echo "📦 Pulling latest release from Git..."
git pull origin main --ff-only

# 3. Install production PHP dependencies
echo "🐘 Installing Composer dependencies (no-dev, optimized)..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run database migrations safely (never migrate:fresh or db:wipe)
echo "🗄️ Running pending database migrations..."
php artisan migrate --force --no-interaction

# 5. Clear and rebuild production application caches
echo "⚡ Rebuilding production caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart background queue workers
echo "🔄 Signaling background queue workers to restart..."
php artisan queue:restart

# 7. Verification / Health Check Output
echo "✅ Production deployment completed successfully!"
echo "Current APP_ENV: $(php artisan config:show app.env | tail -n 1 || echo 'production')"

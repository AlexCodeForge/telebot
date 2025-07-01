#!/bin/bash
set -e

echo "🐳 Starting TeleBot Docker Container..."

# Wait for any dependent services (though none in this case)
sleep 2

# Ensure we're in the correct directory
cd /var/www/html

# Create required directories if they don't exist
echo "📁 Creating required directories..."
mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views
mkdir -p bootstrap/cache
mkdir -p database

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "📁 Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Generate Laravel app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "🔑 Generating Laravel application key..."
    php artisan key:generate --force
fi

# Set proper permissions (critical for Laravel)
echo "🔐 Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chmod 664 /var/www/html/database/database.sqlite

# Clear all caches first (prevents issues)
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
php artisan route:clear || true

# Check if database is accessible
echo "🔍 Checking database connection..."
if ! php artisan migrate:status &>/dev/null; then
    echo "📊 Running initial database migrations..."
    php artisan migrate --force
else
    echo "📊 Running database migrations..."
    php artisan migrate --force
fi

# Seed database with admin user and sample videos
echo "🌱 Seeding database..."
php artisan db:seed --force || echo "⚠️  Seeding completed (some seeders may have already run)"

# Cache configuration for production (only after migrations)
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Install/update storage link
echo "🔗 Creating storage link..."
php artisan storage:link || echo "⚠️  Storage link already exists"

# Final permission check
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

echo "✅ Laravel application setup complete!"
echo "🌐 Application will be available on port 8000"
echo "🔧 Nginx Proxy Manager admin panel will be available on port 81"
echo "   Default credentials: admin@example.com / changeme"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

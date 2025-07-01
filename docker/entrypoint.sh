#!/bin/sh
set -e

echo "🐳 Starting TeleBot Docker Container..."

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "📁 Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Generate Laravel app key if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating Laravel application key..."
    cd /var/www/html
    php artisan key:generate --force
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Run Laravel setup commands
echo "🚀 Setting up Laravel application..."
cd /var/www/html

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Run migrations
echo "📊 Running database migrations..."
php artisan migrate --force

# Seed database with admin user and sample videos
echo "🌱 Seeding database..."
php artisan db:seed --force

# Cache configuration for production
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Laravel application setup complete!"
echo "🌐 Application will be available on port 8000"
echo "🔧 Nginx Proxy Manager admin panel will be available on port 81"
echo "   Default credentials: admin@example.com / changeme"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

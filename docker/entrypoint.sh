#!/bin/bash
set -e

echo "🐳 Starting TeleBot Docker Container..."

# Wait for any dependent services (though none in this case)
sleep 2

# Ensure we're in the correct directory
cd /var/www/html

# Create required directories with proper permissions first
echo "📁 Creating required directories..."
mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views
mkdir -p bootstrap/cache
mkdir -p database

# Set ownership BEFORE creating database file
echo "🔐 Setting initial ownership..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Create SQLite database if it doesn't exist with proper permissions
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "📁 Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
else
    echo "📁 Database file exists, fixing permissions..."
    chown www-data:www-data /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Ensure database directory has proper permissions
chmod 775 /var/www/html/database

# Generate Laravel app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating Laravel application key..."
    php artisan key:generate --force
else
    echo "🔑 Using existing Laravel application key: ${APP_KEY:0:20}..."
fi

# Set proper permissions again (critical for Laravel)
echo "🔐 Setting comprehensive file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod 664 /var/www/html/database/database.sqlite

# Test database file accessibility
echo "🔍 Testing database file access..."
if [ ! -w /var/www/html/database/database.sqlite ]; then
    echo "❌ Database file is not writable! Fixing..."
    chmod 666 /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Clear all caches first (prevents issues) - but skip database operations for now
echo "🧹 Clearing file-based caches..."
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true

# Test basic database connectivity before proceeding
echo "🔍 Testing basic database connectivity..."
if ! php -r "
try {
    \$pdo = new PDO('sqlite:/var/www/html/database/database.sqlite');
    echo 'Database connection successful\n';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"; then
    echo "❌ Database connectivity test failed!"
    exit 1
fi

# Now try cache operations
echo "🧹 Clearing database caches..."
php artisan cache:clear || echo "⚠️  Cache clear failed, continuing..."

# Check if database is accessible
echo "🔍 Checking database migration status..."
if ! php artisan migrate:status &>/dev/null; then
    echo "📊 Running initial database migrations..."
    php artisan migrate --force --step
else
    echo "📊 Running database migrations..."
    php artisan migrate --force
fi

# Seed database with admin user and sample videos
echo "🌱 Seeding database..."
php artisan db:seed --force --class=DatabaseSeeder || echo "⚠️  Seeding completed (some seeders may have already run)"

# Cache configuration for production (only after migrations)
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Install/update storage link
echo "🔗 Creating storage link..."
php artisan storage:link || echo "⚠️  Storage link already exists"

# Final permission check
echo "🔐 Final permission check..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod 775 /var/www/html/database
chmod 664 /var/www/html/database/database.sqlite

echo "✅ Laravel application setup complete!"
echo "🌐 Application will be available on port 8000"
echo "🔧 Nginx Proxy Manager admin panel will be available on port 81"
echo "   Default credentials: admin@example.com / changeme"

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

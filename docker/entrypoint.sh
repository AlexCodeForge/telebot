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

# Regenerate composer autoload (critical for migrations and seeders)
echo "🔄 Regenerating composer autoload..."
composer dump-autoload --optimize

# Debug: List available migrations
echo "🔍 Available migration files:"
ls -la /var/www/html/database/migrations/ || echo "❌ No migrations directory!"

# Debug: List available seeders
echo "🔍 Available seeder files:"
ls -la /var/www/html/database/seeders/ || echo "❌ No seeders directory!"

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
    echo "📊 Database appears empty, running initial migrations..."
    if ! php artisan migrate --force; then
        echo "❌ Initial migrations failed! Trying fresh migration..."
        # Delete database and recreate
        rm -f /var/www/html/database/database.sqlite
        touch /var/www/html/database/database.sqlite
        chown www-data:www-data /var/www/html/database/database.sqlite
        chmod 664 /var/www/html/database/database.sqlite

        # Try fresh migration
        php artisan migrate:fresh --force || echo "❌ Fresh migration also failed"
    fi
else
    echo "📊 Database has migrations, checking if we need to run new ones..."
    # Force check if any migrations show as nothing to migrate
    MIGRATE_OUTPUT=$(php artisan migrate --force 2>&1)
    echo "$MIGRATE_OUTPUT"
    if echo "$MIGRATE_OUTPUT" | grep -q "Nothing to migrate"; then
        echo "⚠️  'Nothing to migrate' detected but tables might be missing. Forcing fresh migration..."
        php artisan migrate:fresh --force || echo "❌ Forced fresh migration failed"
    fi
fi

# Verify critical tables exist
echo "🔍 Verifying critical tables exist..."
php -r "
try {
    \$pdo = new PDO('sqlite:/var/www/html/database/database.sqlite');
    \$tables = ['users', 'sessions', 'migrations'];
    foreach (\$tables as \$table) {
        \$result = \$pdo->query(\"SELECT name FROM sqlite_master WHERE type='table' AND name='{\$table}'\");
        if (\$result->rowCount() > 0) {
            echo \"✅ Table {\$table} exists\n\";
        } else {
            echo \"❌ Table {\$table} MISSING!\n\";
        }
    }
} catch (Exception \$e) {
    echo \"❌ Database verification failed: \" . \$e->getMessage() . \"\n\";
}
"

# Seed database with admin user and sample videos
echo "🌱 Seeding database..."
if php artisan db:seed --force --class=DatabaseSeeder; then
    echo "✅ Database seeding completed successfully!"
else
    echo "⚠️  DatabaseSeeder failed, trying individual seeders..."
    if php artisan db:seed --force --class=AdminUserSeeder; then
        echo "✅ Admin user seeder completed"
    else
        echo "❌ Admin user seeder failed"
    fi
    if php artisan db:seed --force --class=VideosTableSeeder; then
        echo "✅ Videos seeder completed"
    else
        echo "❌ Videos seeder failed"
    fi
fi

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

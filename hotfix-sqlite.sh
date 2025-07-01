#!/bin/bash
set -e

echo "🔧 TeleBot SQLite Hotfix"
echo "========================"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "❌ This script must be run as root (use sudo)"
   exit 1
fi

INSTALL_PATH="/opt/telebot"

echo "🔍 Diagnosing SQLite issue..."

# Check if sqlite3 command exists
if ! command -v sqlite3 &> /dev/null; then
    echo "❌ sqlite3 command not found - installing..."
    apt-get update
    apt-get install -y sqlite3
    echo "✅ sqlite3 command installed"
else
    echo "✅ sqlite3 command already available"
fi

# Navigate to installation directory
if [[ -d "$INSTALL_PATH" ]]; then
    cd "$INSTALL_PATH"
    echo "✅ Found TeleBot installation at $INSTALL_PATH"
else
    echo "❌ TeleBot installation not found at $INSTALL_PATH"
    exit 1
fi

# Fix database permissions
echo "🔧 Fixing database permissions..."
chown -R www-data:www-data database/
chmod 775 database/
chmod 664 database/database.sqlite 2>/dev/null || touch database/database.sqlite && chmod 664 database/database.sqlite

# Clear any cache issues
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear

# Retry database setup
echo "🗄️ Setting up database..."
php artisan migrate:fresh --force
php artisan db:seed --force

# Cache configuration
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
echo "🔄 Restarting services..."
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart telebot-queue

echo ""
echo "✅ Hotfix completed!"
echo "🌐 Your site should now be working at your domain"
echo ""

# Test the fix
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302"; then
    echo "✅ Web server is responding correctly"
else
    echo "⚠️ Web server may still have issues - check logs:"
    echo "   tail -f $INSTALL_PATH/storage/logs/laravel.log"
fi

#!/bin/bash

echo "🔍 TeleBot Debug Information"
echo "============================"

cd /opt/telebot

echo "📋 Container Status:"
docker compose ps

echo ""
echo "📊 App Container Details:"
docker inspect telebot-app | grep -A 10 -B 2 "Status\|Health\|ExitCode"

echo ""
echo "🐛 PHP Error Logs (if any):"
docker compose exec app cat /var/log/nginx/error.log 2>/dev/null || echo "No nginx error log found"

echo ""
echo "🐛 Laravel Logs:"
docker compose exec app cat /var/www/html/storage/logs/laravel.log 2>/dev/null || echo "No Laravel log found"

echo ""
echo "🔍 Environment Configuration:"
docker compose exec app env | grep -E "APP_|DB_|LOG_" | sort

echo ""
echo "📁 Database File Status:"
docker compose exec app ls -la /var/www/html/database/

echo ""
echo "🔧 PHP Configuration:"
docker compose exec app php -m | grep -E "sqlite|pdo"

echo ""
echo "🌐 Testing Direct PHP:"
docker compose exec app php -r "
echo 'PHP Version: ' . PHP_VERSION . \"\n\";
try {
    \$pdo = new PDO('sqlite:/var/www/html/database/database.sqlite');
    echo 'Database: OK\n';
} catch (Exception \$e) {
    echo 'Database Error: ' . \$e->getMessage() . \"\n\";
}
try {
    \$config = include '/var/www/html/config/app.php';
    echo 'Config loaded: OK\n';
} catch (Exception \$e) {
    echo 'Config Error: ' . \$e->getMessage() . \"\n\";
}
"

echo ""
echo "🔍 Recent Application Logs (last 50 lines):"
docker compose logs app --tail=50

echo ""
echo "🌐 Testing HTTP Response with curl:"
docker compose exec app curl -v http://localhost/ 2>&1 || echo "Curl test failed"

echo ""
echo "🔧 PHP-FPM Status:"
docker compose exec app ps aux | grep php

echo ""
echo "🔧 Nginx Status:"
docker compose exec app ps aux | grep nginx

echo ""
echo "📝 Nginx Configuration Test:"
docker compose exec app nginx -t 2>&1 || echo "Nginx config test failed"

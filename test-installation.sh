#!/bin/bash

echo "🧪 TeleBot Installation Test Script"
echo "==================================="

DOMAIN="${1:-test.example.com}"
INSTALL_PATH="${2:-/opt/telebot}"

echo "Testing installation for domain: $DOMAIN"
echo "Installation path: $INSTALL_PATH"
echo ""

# Check if directory exists
if [[ -d "$INSTALL_PATH" ]]; then
    echo "✅ Installation directory exists"
else
    echo "❌ Installation directory missing"
    exit 1
fi

cd "$INSTALL_PATH"

# Check if it's a Laravel app
if [[ -f "artisan" ]]; then
    echo "✅ Laravel application found"
else
    echo "❌ Laravel application missing"
    exit 1
fi

# Check .env file
if [[ -f ".env" ]]; then
    echo "✅ Environment file exists"

    # Check for required variables
    if grep -q "APP_KEY=" .env && ! grep -q "APP_KEY=$" .env; then
        echo "✅ App key is set"
    else
        echo "❌ App key is missing"
    fi

    if grep -q "TELEGRAM_BOT_TOKEN=" .env; then
        echo "✅ Telegram token configured"
    else
        echo "❌ Telegram token missing"
    fi

    if grep -q "STRIPE_KEY=" .env; then
        echo "✅ Stripe keys configured"
    else
        echo "❌ Stripe keys missing"
    fi
else
    echo "❌ Environment file missing"
    exit 1
fi

# Check database
if [[ -f "database/database.sqlite" ]]; then
    echo "✅ SQLite database exists"

    # Check if database has tables
    if sqlite3 database/database.sqlite ".tables" | grep -q "users"; then
        echo "✅ Database tables created"
    else
        echo "❌ Database tables missing"
    fi
else
    echo "❌ SQLite database missing"
fi

# Check if services are running
if systemctl is-active --quiet php8.2-fpm; then
    echo "✅ PHP-FPM is running"
else
    echo "❌ PHP-FPM is not running"
fi

if systemctl is-active --quiet nginx; then
    echo "✅ Nginx is running"
else
    echo "❌ Nginx is not running"
fi

if systemctl is-active --quiet telebot-queue; then
    echo "✅ Queue worker is running"
else
    echo "❌ Queue worker is not running"
fi

# Test web server response
echo ""
echo "🌐 Testing web server..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)
if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "302" ]]; then
    echo "✅ Web server responding (HTTP $HTTP_CODE)"
else
    echo "❌ Web server not responding (HTTP $HTTP_CODE)"
fi

# Test HTTPS if available
if curl -k -s -o /dev/null -w "%{http_code}" "https://$DOMAIN" 2>/dev/null | grep -q "200\|302"; then
    echo "✅ HTTPS is working"
else
    echo "⚠️  HTTPS may not be configured yet"
fi

echo ""
echo "�� Test completed!"

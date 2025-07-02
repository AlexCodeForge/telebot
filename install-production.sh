#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function for colored output
log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

echo "🚀 TeleBot Production Installer/Updater"
echo "======================================"

# Parse command line arguments (all optional now)
DOMAIN="$1"
TELEGRAM_TOKEN="$2"
STRIPE_PUBLIC="$3"
STRIPE_SECRET="$4"
INSTALL_PATH="${5:-/opt/telebot}"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root (use sudo)"
   echo ""
   echo "🔄 For Updates (if TeleBot is already installed):"
   echo "  curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash"
   echo ""
   echo "🆕 For Fresh Installation:"
   echo "  curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash -s -- \\"
   echo "    <domain> \\"
   echo "    <telegram-token> \\"
   echo "    <stripe-public-key> \\"
   echo "    <stripe-secret-key>"
   echo ""
   exit 1
fi

# Check if TeleBot is already installed
if [[ -d "$INSTALL_PATH" && -f "$INSTALL_PATH/.env" && -f "$INSTALL_PATH/artisan" ]]; then
    log_info "TeleBot installation detected at $INSTALL_PATH"
    log_info "Performing update instead of fresh installation..."

    echo "🔄 TeleBot Update Process"
    echo "========================="

    cd "$INSTALL_PATH"

    # Create backup
    log_info "Creating backup..."
    BACKUP_DIR="/opt/telebot-backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    cp -r .env "$BACKUP_DIR/" 2>/dev/null || true
    cp -r storage/logs "$BACKUP_DIR/" 2>/dev/null || true
    cp -r database/database.sqlite "$BACKUP_DIR/" 2>/dev/null || true
    log_success "Backup created at $BACKUP_DIR"

    # Stop services temporarily
    log_info "Stopping services..."
    systemctl stop telebot-queue 2>/dev/null || true

    # Check git status and handle updates properly
    log_info "Checking for updates..."
    git remote update

    # Get current branch and remote status
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
    LOCAL_COMMIT=$(git rev-parse HEAD)
    REMOTE_COMMIT=$(git rev-parse origin/$CURRENT_BRANCH)

    if [[ "$LOCAL_COMMIT" == "$REMOTE_COMMIT" ]]; then
        log_info "Already up to date!"
    else
        log_info "Updates available. Updating from GitHub..."

        # Handle potential conflicts by stashing local changes
        if [[ -n $(git status --porcelain) ]]; then
            log_warning "Local changes detected. Stashing them..."
            git stash push -m "Auto-stash before update $(date)"
        fi

        # Force pull latest changes
        git reset --hard origin/$CURRENT_BRANCH
        git pull origin $CURRENT_BRANCH

        log_success "Code updated successfully"
    fi

    # Update dependencies
    log_info "Updating PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction

    # Update frontend assets
    log_info "Updating frontend assets..."
    if [[ -f "package.json" ]]; then
        npm ci --silent 2>/dev/null || npm install --silent
        npm run build --silent 2>/dev/null || npm run production --silent
        log_success "Frontend assets updated"
    fi

    # Run migrations
    log_info "Running database migrations..."
    php artisan migrate --force

    # Clear and rebuild caches
    log_info "Optimizing application..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Fix permissions
    log_info "Fixing file permissions..."
    chown -R www-data:www-data "$INSTALL_PATH"
    chmod -R 755 "$INSTALL_PATH"
    chmod -R 775 "$INSTALL_PATH/storage"
    chmod -R 775 "$INSTALL_PATH/bootstrap/cache"

    # Restart services
    log_info "Restarting services..."
    systemctl restart php8.2-fpm
    systemctl restart nginx
    systemctl start telebot-queue

    # Wait for services
    sleep 3

    # Health checks
    log_info "Performing health checks..."
    if systemctl is-active --quiet php8.2-fpm; then
        log_success "PHP-FPM is running"
    else
        log_error "PHP-FPM failed to start"
    fi

    if systemctl is-active --quiet nginx; then
        log_success "Nginx is running"
    else
        log_error "Nginx failed to start"
    fi

    if systemctl is-active --quiet telebot-queue; then
        log_success "Queue worker is running"
    else
        log_warning "Queue worker failed to start - attempting restart..."
        systemctl restart telebot-queue
    fi

    # Test application
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302"; then
        log_success "Application is responding correctly"
    else
        log_warning "Application may not be responding correctly"
    fi

    # Get current info
    APP_URL=$(grep "^APP_URL=" .env 2>/dev/null | cut -d'=' -f2 || echo "Not configured")
    CURRENT_COMMIT=$(git rev-parse --short HEAD)

    echo ""
    echo "🎉 TeleBot Update Complete!"
    echo "=========================="
    echo ""
    echo "📊 Update Summary:"
    echo "  🌐 App URL: $APP_URL"
    echo "  📁 Install Path: $INSTALL_PATH"
    echo "  📝 Commit: $CURRENT_COMMIT"
    echo "  💾 Backup: $BACKUP_DIR"
    echo ""
    echo "🔧 Useful Commands:"
    echo "  📊 Check services: systemctl status telebot-queue"
    echo "  📝 View logs: tail -f $INSTALL_PATH/storage/logs/laravel.log"
    echo "  🔄 Restart all: systemctl restart php8.2-fpm nginx telebot-queue"
    echo ""
    echo "🔗 Access Points:"
    echo "  🏠 Homepage: $APP_URL"
    echo "  👑 Admin Panel: $APP_URL/admin/videos/manage"
    echo "  🤖 Bot Emulator: $APP_URL/bot-test"
    echo ""

    log_success "Update completed successfully! 🚀"
    log_info "All configuration is managed through the app's admin panel"
    exit 0
fi

# Fresh installation - validate required parameters
if [[ -z "$DOMAIN" || -z "$TELEGRAM_TOKEN" || -z "$STRIPE_PUBLIC" || -z "$STRIPE_SECRET" ]]; then
    log_error "Fresh installation requires all parameters!"
    echo ""
    echo "Usage for fresh installation:"
    echo "curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash -s -- \\"
    echo "  <domain> \\"
    echo "  <telegram-token> \\"
    echo "  <stripe-public-key> \\"
    echo "  <stripe-secret-key> \\"
    echo "  [install-path]"
    echo ""
    echo "Example:"
    echo "curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash -s -- \\"
    echo "  yourdomain.com \\"
    echo "  \"1234567890:ABCDefGhIjKlMnOpQrStUvWxYz\" \\"
    echo "  \"pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx\" \\"
    echo "  \"sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx\""
    echo ""
    exit 1
fi

# Continue with fresh installation if not already installed
log_info "No existing installation found. Proceeding with fresh installation..."

log_info "Configuration:"
echo "  🌐 Domain: $DOMAIN"
echo "  📱 Telegram Token: ${TELEGRAM_TOKEN:0:20}..."
echo "  💳 Stripe Public: ${STRIPE_PUBLIC:0:20}..."
echo "  🔐 Stripe Secret: ${STRIPE_SECRET:0:20}..."
echo "  📁 Install Path: $INSTALL_PATH"
echo ""

# Function to wait for package locks
wait_for_package_locks() {
    log_info "Waiting for package manager locks to be released..."
    while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || sudo fuser /var/lib/dpkg/lock >/dev/null 2>&1; do
        sleep 5
    done
}

# Fix any existing package manager issues
log_info "Preparing package manager..."
wait_for_package_locks
export DEBIAN_FRONTEND=noninteractive

# Kill any hanging package processes
pkill -f apt-get || true
pkill -f dpkg || true

# Remove locks
rm -f /var/lib/dpkg/lock-frontend
rm -f /var/lib/dpkg/lock
rm -f /var/cache/apt/archives/lock

# Configure dpkg
dpkg --configure -a

# Update package lists
log_info "Updating package lists..."
apt-get update

# Install essential packages
log_info "Installing essential packages..."
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    ca-certificates \
    lsb-release \
    gnupg \
    ufw \
    sqlite3

# Install PHP 8.2
if ! command_exists php || [[ $(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1-2) != "8.2" ]]; then
    log_info "Installing PHP 8.2..."

    # Add PHP repository
    add-apt-repository -y ppa:ondrej/php
    apt-get update

    # Install PHP and required extensions
    apt-get install -y \
        php8.2 \
        php8.2-cli \
        php8.2-fpm \
        php8.2-mysql \
        php8.2-sqlite3 \
        php8.2-xml \
        php8.2-mbstring \
        php8.2-curl \
        php8.2-zip \
        php8.2-intl \
        php8.2-bcmath \
        php8.2-gd \
        php8.2-tokenizer \
        php8.2-dom \
        php8.2-fileinfo
else
    log_success "PHP 8.2 already installed"
fi

# Install Composer
if ! command_exists composer; then
    log_info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
else
    log_success "Composer already installed"
fi

# Install Node.js and NPM
if ! command_exists node; then
    log_info "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
else
    log_success "Node.js already installed"
fi

# Install Nginx
if ! command_exists nginx; then
    log_info "Installing Nginx..."
    apt-get install -y nginx
    systemctl enable nginx
else
    log_success "Nginx already installed"
fi

# Install Certbot for SSL
if ! command_exists certbot; then
    log_info "Installing Certbot..."
    apt-get install -y certbot python3-certbot-nginx
else
    log_success "Certbot already installed"
fi

# Configure firewall
log_info "Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Clean up any previous installation
log_info "Cleaning up previous installation..."
if [[ -d "$INSTALL_PATH" ]]; then
    log_warning "Previous installation found, removing completely..."

    # Stop any running services
    systemctl stop telebot-queue 2>/dev/null || true
    systemctl stop php8.2-fpm 2>/dev/null || true
    systemctl stop nginx 2>/dev/null || true

    # Remove systemd service
    systemctl disable telebot-queue 2>/dev/null || true
    rm -f /etc/systemd/system/telebot-queue.service
    systemctl daemon-reload

        # Remove nginx config
    rm -f /etc/nginx/sites-enabled/telebot
    rm -f /etc/nginx/sites-available/telebot

    # Remove SSL certificates if they exist
    certbot delete --cert-name "$DOMAIN" --non-interactive 2>/dev/null || true

    # Remove installation directory
    rm -rf "$INSTALL_PATH"

    log_success "Previous installation cleaned up"
fi

# Create fresh application directory
log_info "Setting up fresh application directory..."
mkdir -p "$INSTALL_PATH"
cd "$INSTALL_PATH"

# Clone repository
log_info "Cloning repository..."
git clone https://github.com/AlexCodeForge/telebot.git .

# Create environment file
log_info "Creating environment configuration..."
cat > .env << EOF
APP_NAME=TeleBot
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://$DOMAIN

DB_CONNECTION=sqlite
DB_DATABASE=$INSTALL_PATH/database/database.sqlite

TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
TELEGRAM_WEBHOOK_URL=https://$DOMAIN/telegram/webhook

STRIPE_KEY=$STRIPE_PUBLIC
STRIPE_SECRET=$STRIPE_SECRET
CASHIER_CURRENCY=usd

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

LOG_CHANNEL=single
LOG_LEVEL=error
LOG_SINGLE_PATH=$INSTALL_PATH/storage/logs/laravel.log

BROADCAST_DRIVER=log
MAIL_MAILER=log

VITE_APP_NAME=TeleBot
EOF

# Install PHP dependencies (with dev dependencies for seeding)
log_info "Installing PHP dependencies..."
composer install --optimize-autoloader

# Install and build frontend assets
log_info "Installing and building frontend assets..."
npm ci
npm run build

# Create necessary directories
log_info "Creating necessary directories..."
mkdir -p database
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Create SQLite database
log_info "Creating SQLite database..."
touch database/database.sqlite

# Set proper permissions
log_info "Setting file permissions..."
chown -R www-data:www-data "$INSTALL_PATH"
chmod -R 755 "$INSTALL_PATH"
chmod -R 775 "$INSTALL_PATH"/storage
chmod -R 775 "$INSTALL_PATH"/bootstrap/cache
chmod -R 775 "$INSTALL_PATH"/database
chmod 664 "$INSTALL_PATH"/database/database.sqlite

# Generate Laravel application key
log_info "Generating Laravel application key..."
php artisan key:generate --force

# Run database migrations and seed
log_info "Setting up database..."

# Ensure sqlite3 is available (fix for common issue)
if ! command_exists sqlite3; then
    log_warning "Installing missing sqlite3 command..."
    apt-get install -y sqlite3
fi

php artisan migrate:fresh --force
php artisan db:seed --force

# Reinstall production dependencies (remove dev packages)
log_info "Optimizing for production..."
composer install --no-dev --optimize-autoloader

# Cache configuration
log_info "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Configure Nginx
log_info "Configuring Nginx..."
cat > /etc/nginx/sites-available/telebot << EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $INSTALL_PATH/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# Enable site
ln -sf /etc/nginx/sites-available/telebot /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
if nginx -t; then
    log_success "Nginx configuration is valid"
else
    log_error "Nginx configuration is invalid"
    exit 1
fi

# Configure PHP-FPM
log_info "Configuring PHP-FPM..."
cat > /etc/php/8.2/fpm/pool.d/www.conf << EOF
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
EOF

# Restart services
log_info "Starting services..."
systemctl enable php8.2-fpm
systemctl enable nginx
systemctl restart php8.2-fpm
systemctl restart nginx

# Wait a moment for services to start
sleep 3

# Get SSL certificate
log_info "Obtaining SSL certificate..."
if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN" --redirect; then
    log_success "SSL certificate obtained successfully"
else
    log_warning "SSL certificate generation failed, but installation continues"
fi

# Set up Telegram webhook
log_info "Setting up Telegram webhook..."
WEBHOOK_URL="https://$DOMAIN/telegram/webhook"
if curl -s "https://api.telegram.org/bot$TELEGRAM_TOKEN/setWebhook?url=$WEBHOOK_URL" | grep -q '"ok":true'; then
    log_success "Telegram webhook configured successfully"
else
    log_warning "Failed to set Telegram webhook - check your bot token"
fi

# Create systemd service for queue worker
log_info "Creating queue worker service..."
cat > /etc/systemd/system/telebot-queue.service << EOF
[Unit]
Description=TeleBot Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$INSTALL_PATH
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable telebot-queue
systemctl start telebot-queue

# Final status check
log_info "Performing final health checks..."

# Check if PHP-FPM is running
if systemctl is-active --quiet php8.2-fpm; then
    log_success "PHP-FPM is running"
else
    log_error "PHP-FPM is not running"
fi

# Check if Nginx is running
if systemctl is-active --quiet nginx; then
    log_success "Nginx is running"
else
    log_error "Nginx is not running"
fi

# Check if queue worker is running
if systemctl is-active --quiet telebot-queue; then
    log_success "Queue worker is running"
else
    log_warning "Queue worker is not running"
fi

# Test application
log_info "Testing application..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302"; then
    log_success "Application is responding"
else
    log_warning "Application may not be responding correctly"
fi

echo ""
echo "🎉 TeleBot Installation Complete!"
echo "=================================="
echo ""
echo "📊 Installation Summary:"
echo "  🌐 Domain: https://$DOMAIN"
echo "  📁 Path: $INSTALL_PATH"
echo "  🗄️  Database: SQLite ($INSTALL_PATH/database/database.sqlite)"
echo "  📱 Telegram Webhook: $WEBHOOK_URL"
echo ""
echo "👤 Default Admin Account:"
echo "  📧 Email: admin@telebot.com"
echo "  🔑 Password: admin123"
echo ""
echo "🔧 Management Commands:"
echo "  📊 Check status: systemctl status telebot-queue"
echo "  🔄 Update app: curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash"
echo "  📝 View logs: tail -f $INSTALL_PATH/storage/logs/laravel.log"
echo "  🔄 Restart services: systemctl restart php8.2-fpm nginx telebot-queue"
echo ""
echo "🔗 Important URLs:"
echo "  🏠 Application: https://$DOMAIN"
echo "  👑 Admin Panel: https://$DOMAIN/admin/videos/manage"
echo "  🤖 Bot Emulator: https://$DOMAIN/bot-test"
echo ""
echo "⚠️  SECURITY REMINDER:"
echo "  🔑 Change the default admin password immediately!"
echo "  🔐 Keep your Telegram bot token and Stripe keys secure!"
echo ""

log_success "Installation completed successfully! 🚀"

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

echo "🔄 TeleBot Production Updater"
echo "============================="

INSTALL_PATH="${1:-/opt/telebot}"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root (use sudo)"
   echo "Usage: curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/update-production.sh | sudo bash"
   exit 1
fi

# Check if TeleBot is installed
if [[ ! -d "$INSTALL_PATH" || ! -f "$INSTALL_PATH/.env" || ! -f "$INSTALL_PATH/artisan" ]]; then
    log_error "TeleBot installation not found at $INSTALL_PATH"
    log_info "Please run the full installer first:"
    echo "curl -fsSL https://raw.githubusercontent.com/AlexCodeForge/telebot/master/install-production.sh | sudo bash"
    exit 1
fi

log_info "TeleBot installation found at $INSTALL_PATH"
cd "$INSTALL_PATH"

# Backup current state
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

# Update PHP dependencies
log_info "Updating PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Update Node.js dependencies and build assets
log_info "Updating frontend assets..."
if [[ -f "package.json" ]]; then
    npm ci --silent 2>/dev/null || npm install --silent
    npm run build --silent 2>/dev/null || npm run production --silent
    log_success "Frontend assets updated"
else
    log_info "No package.json found, skipping npm updates"
fi

# Run database migrations
log_info "Running database migrations..."
php artisan migrate --force

# Clear and rebuild all caches
log_info "Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild optimized caches
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

# Wait for services to start
sleep 3

# Health checks
log_info "Performing health checks..."

# Check if PHP-FPM is running
if systemctl is-active --quiet php8.2-fpm; then
    log_success "PHP-FPM is running"
else
    log_error "PHP-FPM failed to start"
fi

# Check if Nginx is running
if systemctl is-active --quiet nginx; then
    log_success "Nginx is running"
else
    log_error "Nginx failed to start"
fi

# Check if queue worker is running
if systemctl is-active --quiet telebot-queue; then
    log_success "Queue worker is running"
else
    log_warning "Queue worker failed to start"
    log_info "Attempting to restart queue worker..."
    systemctl restart telebot-queue
fi

# Test application response
log_info "Testing application..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302"; then
    log_success "Application is responding correctly"
else
    log_warning "Application may not be responding correctly"
fi

# Get app info for final report
APP_URL=$(grep "^APP_URL=" .env 2>/dev/null | cut -d'=' -f2 || echo "Not configured")
CURRENT_COMMIT=$(git rev-parse --short HEAD)
CURRENT_VERSION=$(git describe --tags 2>/dev/null || echo "No tags")

echo ""
echo "🎉 TeleBot Update Complete!"
echo "=========================="
echo ""
echo "📊 Update Summary:"
echo "  🌐 App URL: $APP_URL"
echo "  📁 Install Path: $INSTALL_PATH"
echo "  🏷️  Version: $CURRENT_VERSION"
echo "  📝 Commit: $CURRENT_COMMIT"
echo "  💾 Backup: $BACKUP_DIR"
echo ""
echo "🔧 Useful Commands:"
echo "  📊 Check services: systemctl status telebot-queue"
echo "  📝 View logs: tail -f $INSTALL_PATH/storage/logs/laravel.log"
echo "  🔄 Restart all: systemctl restart php8.2-fpm nginx telebot-queue"
echo "  🔙 Restore backup: cp -r $BACKUP_DIR/.env $INSTALL_PATH/"
echo ""
echo "🔗 Access Points:"
echo "  🏠 Homepage: $APP_URL"
echo "  👑 Admin Panel: $APP_URL/admin/videos/manage"
echo "  🤖 Bot Emulator: $APP_URL/bot-test"
echo ""

log_success "Update completed successfully! 🚀"
log_info "All configuration is now managed through the app's admin panel"
